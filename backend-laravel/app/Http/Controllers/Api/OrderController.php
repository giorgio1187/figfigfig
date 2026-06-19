<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\RestaurantTable;
use App\Models\Product;
use App\Models\Ingredient;
use App\Models\TableGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class OrderController extends Controller
{
    /**
     * GET /api/orders
     *
     * Obtiene el listado completo de órdenes con filtros opcionales.
     */
    public function getAll(Request $request): JsonResponse
    {
        $query = Order::with(['table', 'user', 'items.product']);

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('table_id')) {
            $query->where('table_id', $request->query('table_id'));
        }

        if ($request->filled('session_id')) {
            $query->where('session_id', $request->query('session_id'));
        }

        $orders = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => OrderResource::collection($orders),
        ]);
    }

    /**
     * GET /api/orders/{id}
     *
     * Obtiene el detalle de una orden específica.
     */
    public function getById(string $id): JsonResponse
    {
        $order = Order::with(['table', 'user', 'items.product'])->find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Orden no encontrada.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new OrderResource($order),
        ]);
    }

    /**
     * POST /api/orders
     *
     * Crea una nueva orden y descuenta insumos de inventario atómicamente.
     */
    public function create(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'table_id' => ['required_without:session_id', 'nullable', 'uuid', 'exists:restaurant_tables,id'],
            'session_id' => ['required_without:table_id', 'nullable', 'string'],
            'user_id' => ['required', 'uuid', 'exists:users,id'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'uuid', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.notes' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos de la orden inválidos.',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Si se envió table_id pero no session_id, intentar detectar session_id de la mesa
        $tableId = $request->table_id;
        $sessionId = $request->session_id;
        if ($tableId && !$sessionId) {
            $table = RestaurantTable::with('activeGroup')->find($tableId);
            if ($table && $table->activeGroup) {
                $sessionId = $table->activeGroup->session_id;
            }
        }

        // Si se envió session_id pero no table_id, intentar asignar la mesa principal del grupo
        if ($sessionId && !$tableId) {
            $mainGroupEntry = TableGroup::where('session_id', $sessionId)
                ->where('is_main', true)
                ->first();
            if ($mainGroupEntry) {
                $tableId = $mainGroupEntry->table_id;
            }
        }

        // Iniciar transacción de base de datos de manera explícita
        DB::beginTransaction();

        try {
            $itemsData = $request->items;
            $missingIngredients = [];
            $requiredAmounts = []; // ingredient_id => [name, needed, available, ingredient]

            // 1. Agrupar ingredientes requeridos por todos los productos en el request
            foreach ($itemsData as $item) {
                $product = Product::with('recipeIngredients.ingredient')->find($item['product_id']);
                if (!$product)
                    continue;

                foreach ($product->recipeIngredients as $ri) {
                    $needed = (float) $ri->quantity * (int) $item['quantity'];
                    if (!isset($requiredAmounts[$ri->ingredient_id])) {
                        // Bloquear fila del ingrediente para lectura atómica
                        $ingredient = Ingredient::lockForUpdate()->find($ri->ingredient_id);
                        $requiredAmounts[$ri->ingredient_id] = [
                            'ingredient' => $ingredient,
                            'name' => $ingredient ? $ingredient->name : 'Desconocido',
                            'needed' => 0.0,
                            'available' => $ingredient ? (float) $ingredient->stock : 0.0,
                        ];
                    }
                    $requiredAmounts[$ri->ingredient_id]['needed'] += $needed;
                }
            }

            // 2. Verificar si hay stock suficiente en la tabla ingredients
            foreach ($requiredAmounts as $ingId => $data) {
                if ($data['available'] < $data['needed']) {
                    $missingIngredients[] = [
                        'ingredient_id' => $ingId,
                        'name' => $data['name'],
                        'required' => $data['needed'],
                        'available' => $data['available'],
                    ];
                }
            }

            // 3. Si NO hay stock suficiente para algún ingrediente, hacer Rollback y responder con 422
            if (count($missingIngredients) > 0) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'error' => 'Stock insuficiente para procesar la comanda.',
                    'missing' => $missingIngredients,
                ], 422);
            }

            // 4. Crear la Orden
            $order = Order::create([
                'table_id' => $tableId,
                'session_id' => $sessionId,
                'user_id' => $request->user_id,
                'status' => Order::STATUS_PENDING,
                'subtotal' => 0.0,
                'total' => 0.0,
                'notes' => $request->notes,
            ]);

            $subtotal = 0.0;

            // 5. Crear los ítems de la orden y descontar inventario
            foreach ($itemsData as $item) {
                $product = Product::find($item['product_id']);
                $itemSubtotal = (float) $item['unit_price'] * (int) $item['quantity'];
                $subtotal += $itemSubtotal;

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'subtotal' => $itemSubtotal,
                    'notes' => $item['notes'] ?? null,
                ]);
            }

            // 6. Restar la cantidad de stock e ingresar bitácora
            foreach ($requiredAmounts as $ingId => $data) {
                $ingredient = $data['ingredient'];
                if ($ingredient) {
                    $stockBefore = (float) $ingredient->stock;
                    $totalRequired = $data['needed'];
                    $newStock = $stockBefore - $totalRequired;

                    $ingredient->update(['stock' => $newStock]);

                    // Registrar log de auditoría como 'discount' con fallback seguro
                    try {
                        $ingredient->logMovement(
                            'discount',
                            $totalRequired,
                            $stockBefore,
                            $newStock,
                            $order->id,
                            $request->user_id,
                            "Descuento de stock - Comanda #{$order->id}"
                        );
                    } catch (\Illuminate\Database\QueryException $e) {
                        $ingredient->logMovement(
                            'remove',
                            $totalRequired,
                            $stockBefore,
                            $newStock,
                            $order->id,
                            $request->user_id,
                            "Descuento de stock - Comanda #{$order->id}"
                        );
                    }
                }
            }

            // Actualizar totales de la orden
            $order->update([
                'subtotal' => $subtotal,
                'total' => $subtotal,
            ]);

            // Actualizar estado de la mesa física y sus asociadas a ocupadas
            if ($sessionId) {
                $tableIds = TableGroup::where('session_id', $sessionId)->pluck('table_id');
                if ($tableIds->isNotEmpty()) {
                    RestaurantTable::whereIn('id', $tableIds)->update([
                        'status' => RestaurantTable::STATUS_OCCUPIED,
                    ]);
                }
            } else if ($tableId) {
                RestaurantTable::where('id', $tableId)->update([
                    'status' => RestaurantTable::STATUS_OCCUPIED,
                ]);
            }

            DB::commit();

            // Retornar la orden completa
            $order->load(['table', 'user', 'items.product']);
            return response()->json([
                'success' => true,
                'data' => new OrderResource($order),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al registrar la comanda.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/orders (alias para store)
     *
     * Permite guardar comandas usando el método store requerido en especificaciones.
     */
    public function store(Request $request): JsonResponse
    {
        return $this->create($request);
    }

    /**
     * PATCH /api/orders/{id}/send-kitchen
     *
     * Mueve el estado de la orden a 'preparing'.
     */
    public function sendToKitchen(string $id): JsonResponse
    {
        $order = Order::find($id);

        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Orden no encontrada.'], 404);
        }

        if ($order->status !== Order::STATUS_PENDING) {
            return response()->json([
                'success' => false,
                'message' => 'La orden ya fue enviada a la cocina o procesada.',
            ], 409);
        }

        $order->update(['status' => Order::STATUS_PREPARING]);

        return response()->json([
            'success' => true,
            'data' => new OrderResource($order->load(['table', 'user', 'items.product'])),
        ]);
    }

    /**
     * PATCH /api/orders/{id}/mark-ready
     *
     * Marca la orden como lista en cocina (lista para entrega).
     */
    public function markAsReady(string $id): JsonResponse
    {
        $order = Order::find($id);

        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Orden no encontrada.'], 404);
        }

        if ($order->status !== Order::STATUS_PREPARING) {
            return response()->json([
                'success' => false,
                'message' => 'La orden no está en preparación.',
            ], 409);
        }

        $order->update(['status' => Order::STATUS_READY]);

        return response()->json([
            'success' => true,
            'data' => new OrderResource($order->load(['table', 'user', 'items.product'])),
        ]);
    }

    /**
     * PATCH /api/orders/{id}/deliver
     *
     * Marca la orden como entregada en la mesa.
     */
    public function deliver(string $id): JsonResponse
    {
        $order = Order::find($id);

        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Orden no encontrada.'], 404);
        }

        if ($order->status !== Order::STATUS_READY) {
            return response()->json([
                'success' => false,
                'message' => 'La orden no está lista para ser entregada.',
            ], 409);
        }

        $order->update(['status' => Order::STATUS_DELIVERED]);

        return response()->json([
            'success' => true,
            'data' => new OrderResource($order->load(['table', 'user', 'items.product'])),
        ]);
    }

    /**
     * PATCH /api/orders/{id}/pay
     *
     * Registra el pago de una orden y libera la mesa (o grupo).
     */
    public function processPayment(string $id): JsonResponse
    {
        $order = Order::find($id);

        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Orden no encontrada.'], 404);
        }

        if (!in_array($order->status, [Order::STATUS_READY, Order::STATUS_DELIVERED])) {
            return response()->json([
                'success' => false,
                'message' => 'La orden no está en un estado apto para pago.',
            ], 409);
        }

        try {
            DB::transaction(function () use ($order) {
                // Registrar pago
                $order->update([
                    'status' => Order::STATUS_PAID,
                    'paid_at' => now(),
                ]);

                // Liberar la mesa física o el grupo de sesión completo
                if ($order->session_id) {
                    $tableIds = TableGroup::where('session_id', $order->session_id)->pluck('table_id');
                    if ($tableIds->isNotEmpty()) {
                        RestaurantTable::whereIn('id', $tableIds)->update([
                            'status' => RestaurantTable::STATUS_AVAILABLE,
                        ]);
                    }
                    TableGroup::dissolveSession($order->session_id);
                } else if ($order->table_id) {
                    RestaurantTable::where('id', $order->table_id)->update([
                        'status' => RestaurantTable::STATUS_AVAILABLE,
                    ]);
                }
            });

            return response()->json([
                'success' => true,
                'data' => new OrderResource($order->fresh(['table', 'user', 'items.product'])),
                'total' => (float) $order->total,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar el pago.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * PATCH /api/orders/{id}/cancel
     *
     * Cancela la comanda y devuelve los insumos al stock del almacén.
     */
    public function cancel(string $id): JsonResponse
    {
        $order = Order::with('items.product.recipeIngredients.ingredient')->find($id);

        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Orden no encontrada.'], 404);
        }

        if (in_array($order->status, [Order::STATUS_PAID, Order::STATUS_DELIVERED])) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede cancelar una orden que ya fue entregada o pagada.',
            ], 409);
        }

        if (in_array($order->status, [Order::STATUS_PREPARING, Order::STATUS_READY])) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede cancelar una orden en preparación o lista. Use el flujo de merma/devolución.',
            ], 409);
        }

        try {
            DB::transaction(function () use ($order) {
                // 1. Cancelar la Orden
                $order->update(['status' => Order::STATUS_CANCELLED]);

                // 2. Reabastecer los ingredientes consumidos
                foreach ($order->items as $item) {
                    $product = $item->product;
                    if (!$product)
                        continue;

                    foreach ($product->recipeIngredients as $ri) {
                        $totalRequired = (float) $ri->quantity * (int) $item->quantity;
                        $ingredient = $ri->ingredient;

                        if ($ingredient) {
                            $stockBefore = (float) $ingredient->stock;
                            $newStock = $stockBefore + $totalRequired;

                            $ingredient->update(['stock' => $newStock]);

                            // Log de reabastecimiento en inventory_logs
                            $ingredient->logMovement(
                                'add',
                                $totalRequired,
                                $stockBefore,
                                $newStock,
                                $order->id,
                                $order->user_id,
                                "Comanda Cancelada #{$order->id} - Devuelto {$product->name} x{$item->quantity}"
                            );
                        }
                    }
                }

                // 3. Liberar la mesa física si no quedan órdenes activas en la misma mesa/sesión
                if ($order->table_id) {
                    $activeOrdersCount = Order::where('table_id', $order->table_id)
                        ->whereNotIn('status', [Order::STATUS_PAID, Order::STATUS_CANCELLED])
                        ->count();

                    if ($activeOrdersCount === 0) {
                        RestaurantTable::where('id', $order->table_id)->update([
                            'status' => RestaurantTable::STATUS_AVAILABLE,
                        ]);
                    }
                }
            });

            return response()->json([
                'success' => true,
                'data' => new OrderResource($order->fresh(['table', 'user', 'items.product'])),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al cancelar la orden.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/orders/kds
     *
     * Obtiene el listado de pedidos activos para la pantalla de cocina (KDS).
     */
    public function getKDSOrders(): JsonResponse
    {
        $orders = Order::with(['table', 'items.product'])
            ->whereIn('status', [Order::STATUS_PENDING, Order::STATUS_PREPARING])
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => OrderResource::collection($orders),
        ]);
    }

    /**
     * GET /api/orders/pending-payment
     *
     * Obtiene las cuentas pendientes (listas o entregadas, no pagadas).
     */
    public function getPendingPayment(): JsonResponse
    {
        $orders = Order::with(['table', 'user', 'items.product'])
            ->whereIn('status', [Order::STATUS_READY, Order::STATUS_DELIVERED])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => OrderResource::collection($orders),
        ]);
    }

    /**
     * GET /api/orders/revenue/daily
     *
     * Retorna los ingresos recaudados en el día.
     */
    public function getDailyRevenue(Request $request): JsonResponse
    {
        $date = $request->query('date', now()->toDateString());

        // Obtener estadísticas de ingresos para el día específico
        $stats = Order::where('status', Order::STATUS_PAID)
            ->whereDate('paid_at', $date)
            ->selectRaw('COALESCE(SUM(total), 0) as total_revenue, COUNT(*) as order_count')
            ->first();

        $revenue = (float) $stats->total_revenue;
        $count = (int) $stats->order_count;

        return response()->json([
            'success' => true,
            'data' => [
                'revenue' => $revenue,
                'count' => $count,
                'formattedRevenue' => '$' . number_format($revenue, 0, ',', '.'),
            ],
        ]);
    }

    /**
     * PUT /api/orders/{id}/prepare
     *
     * Cambia el estado del pedido a 'preparing'.
     */
    public function prepare(string $id): JsonResponse
    {
        $order = Order::find($id);

        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Orden no encontrada.'], 404);
        }

        $order->update(['status' => Order::STATUS_PREPARING]);

        return response()->json([
            'success' => true,
            'data' => new OrderResource($order->load(['table', 'user', 'items.product'])),
        ]);
    }

    /**
     * PUT /api/orders/{id}/ready
     *
     * Cambia el estado del pedido a 'ready'.
     */
    public function markAsReadyPut(string $id): JsonResponse
    {
        $order = Order::find($id);

        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Orden no encontrada.'], 404);
        }

        $order->update(['status' => Order::STATUS_READY]);

        return response()->json([
            'success' => true,
            'data' => new OrderResource($order->load(['table', 'user', 'items.product'])),
        ]);
    }

    /**
     * PUT /api/orders/{id}/pay
     *
     * Registra el pago y libera la mesa (o grupo completo).
     */
    public function pay(string $id): JsonResponse
    {
        $order = Order::findOrFail($id);
        $order->status = 'paid';
        $order->paid_at = now();
        $order->save();

        if ($order->table_id) {
            DB::table('restaurant_tables')->where('id', $order->table_id)->update(['status' => 'available']);
            DB::table('table_groups')->where('table_id', $order->table_id)->delete();
            cache()->forget('tables_status'); // Limpiar caché para refrescar el front de inmediato
        }

        return response()->json([
            'success' => true,
            'data' => $order
        ]);
    }

    /**
     * GET /api/reports/daily
     *
     * Reporte diario atómico (Dashboard Admin)
     */
    public function dailyReport(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'total_dia' => DB::table('orders')->where('status', 'paid')->whereDate('paid_at', today())->sum('total')
        ]);
    }

    /**
     * GET /api/reports/weekly
     *
     * Reporte de ventas de la semana calendario actual (lunes a domingo).
     */
    public function weeklyReport(): JsonResponse
    {
        $weekStart = now()->startOfWeek(\Carbon\Carbon::MONDAY)->startOfDay();
        $weekEnd = now()->endOfWeek(\Carbon\Carbon::SUNDAY)->endOfDay();

        $rows = Order::query()
            ->where('status', Order::STATUS_PAID)
            ->whereNotNull('paid_at')
            ->whereBetween('paid_at', [$weekStart, $weekEnd])
            ->get(['paid_at', 'total'])
            ->groupBy(fn (Order $order) => $order->paid_at->toDateString());

        $byDay = $rows->map(fn ($group) => (object) [
            'revenue' => $group->sum('total'),
            'order_count' => $group->count(),
        ]);

        $dayLabels = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
        $days = [];
        $totalRevenue = 0;
        $totalOrders = 0;

        $cursor = $weekStart->copy();
        for ($i = 0; $i < 7; $i++) {
            $dateStr = $cursor->toDateString();
            $row = $byDay->get($dateStr);
            $revenue = $row ? (float) $row->revenue : 0;
            $orderCount = $row ? (int) $row->order_count : 0;

            $totalRevenue += $revenue;
            $totalOrders += $orderCount;

            $days[] = [
                'date' => $dateStr,
                'day_label' => $dayLabels[$i],
                'revenue' => $revenue,
                'order_count' => $orderCount,
                'formatted_revenue' => '$' . number_format($revenue, 0, ',', '.'),
            ];

            $cursor->addDay();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'week_start' => $weekStart->toDateString(),
                'week_end' => $weekStart->copy()->addDays(6)->toDateString(),
                'total_revenue' => $totalRevenue,
                'order_count' => $totalOrders,
                'formatted_revenue' => '$' . number_format($totalRevenue, 0, ',', '.'),
                'days' => $days,
            ],
        ]);
    }
}
