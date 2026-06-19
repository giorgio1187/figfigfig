<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Models\Ingredient;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\RestaurantTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AdminController extends Controller
{
    /**
     * GET /api/admin/users
     *
     * Obtiene todos los usuarios del sistema.
     */
    public function getAllUsers(): JsonResponse
    {
        $users = User::orderBy('name')->get();

        return response()->json([
            'success' => true,
            'data' => UserResource::collection($users),
        ]);
    }

    /**
     * GET /api/admin/users/{id}
     *
     * Obtiene los datos de un usuario por su ID.
     */
    public function getUserById(string $id): JsonResponse
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no encontrado.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new UserResource($user),
        ]);
    }

    /**
     * POST /api/admin/users
     *
     * Agrega un nuevo usuario al sistema.
     */
    public function addUser(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'username' => ['required', 'string', 'max:50', 'unique:users,username'],
            'password' => ['required', 'string', 'min:6'],
            'name' => ['required', 'string', 'max:100'],
            'role' => ['required', 'string', 'in:admin,waiter,chef'],
            'station' => ['nullable', 'string', 'max:100'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos de registro inválidos.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = User::create([
            'username' => $request->username,
            'password' => $request->password, // Se auto-hashea por el cast 'hashed' en User.php
            'name' => $request->name,
            'role' => $request->role,
            'station' => $request->input('station', ''),
            'is_active' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => "Usuario '{$user->name}' registrado exitosamente.",
            'data' => new UserResource($user),
        ], 201);
    }

    /**
     * PUT /api/admin/users/{id}
     *
     * Edita los datos de un usuario existente.
     */
    public function editUser(Request $request, string $id): JsonResponse
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no encontrado.',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'username' => ['sometimes', 'string', 'max:50', "unique:users,username,{$id}"],
            'password' => ['nullable', 'string', 'min:6'],
            'name' => ['sometimes', 'string', 'max:100'],
            'role' => ['sometimes', 'string', 'in:admin,waiter,chef'],
            'station' => ['nullable', 'string', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos de edición inválidos.',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Evitar que el administrador principal se desactive o cambie su rol
        if ($user->username === 'admin') {
            if ($request->has('is_active') && !$request->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede desactivar la cuenta del administrador principal.',
                ], 409);
            }
            if ($request->has('role') && $request->role !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede cambiar el rol del administrador principal.',
                ], 409);
            }
        }

        $data = $request->only(['username', 'name', 'role', 'station', 'is_active']);

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Usuario actualizado exitosamente.',
            'data' => new UserResource($user->fresh()),
        ]);
    }

    /**
     * DELETE /api/admin/users/{id}
     *
     * Elimina un usuario por su ID.
     */
    public function deleteUser(string $id): JsonResponse
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no encontrado.',
            ], 404);
        }

        if ($user->username === 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar la cuenta del administrador principal.',
            ], 409);
        }

        $userName = $user->name;
        $user->delete();

        return response()->json([
            'success' => true,
            'message' => "Usuario '{$userName}' eliminado exitosamente.",
        ]);
    }

    /**
     * PATCH /api/admin/users/{id}/toggle-status
     *
     * Activa o desactiva la cuenta de un usuario.
     */
    public function toggleUserStatus(string $id): JsonResponse
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no encontrado.',
            ], 404);
        }

        if ($user->username === 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'No se puede desactivar la cuenta del administrador principal.',
            ], 409);
        }

        $user->update([
            'is_active' => !$user->is_active,
        ]);

        $statusText = $user->is_active ? 'activado' : 'desactivado';

        return response()->json([
            'success' => true,
            'message' => "Usuario '{$user->name}' {$statusText} exitosamente.",
            'data' => new UserResource($user),
        ]);
    }

    /**
     * GET /api/admin/stats
     *
     * Obtiene estadísticas consolidadas del restaurante (Dashboard de alto rendimiento).
     */
    public function getStats(): JsonResponse
    {
        try {
            $today = now()->toDateString();

            // 1. Estadísticas de Usuarios
            $usersStats = DB::selectOne("
                SELECT 
                    COUNT(*) as total, 
                    COUNT(*) FILTER (WHERE is_active = true) as active 
                FROM users
            ");

            // 2. Estadísticas de Ingredientes / Alertas Stock Bajo
            $ingredientsStats = DB::selectOne("
                SELECT 
                    COUNT(*) as total, 
                    COUNT(*) FILTER (WHERE stock < low_stock_threshold) as low_stock 
                FROM ingredients
            ");

            // 3. Estadísticas de Ventas e Ingresos Hoy
            $ordersStats = DB::selectOne("
                SELECT 
                    COUNT(*) as today_total,
                    COUNT(*) FILTER (WHERE status = 'paid' AND DATE(paid_at) = :today) as completed,
                    COALESCE(SUM(total) FILTER (WHERE status = 'paid' AND DATE(paid_at) = :today), 0) as daily_revenue
                FROM orders
            ", ['today' => $today]);

            // 4. Platos más vendidos (Top 5)
            $topProducts = DB::select("
                SELECT 
                    p.id,
                    p.name,
                    SUM(oi.quantity) as quantity_sold,
                    SUM(oi.subtotal) as total_earned
                FROM order_items oi
                JOIN products p ON oi.product_id = p.id
                JOIN orders o ON oi.order_id = o.id
                WHERE o.status = 'paid'
                GROUP BY p.id, p.name
                ORDER BY quantity_sold DESC
                LIMIT 5
            ");

            // 5. Alertas críticas de inventario (detalladas)
            $criticalInventory = DB::select("
                SELECT 
                    id, 
                    name, 
                    stock, 
                    unit, 
                    low_stock_threshold,
                    CASE 
                        WHEN stock = 0 THEN 'critical'
                        WHEN stock < low_stock_threshold * 0.5 THEN 'critical'
                        ELSE 'warning'
                    END as severity
                FROM ingredients
                WHERE stock < low_stock_threshold
                ORDER BY stock ASC
                LIMIT 10
            ");

            // Formatear ingresos diarios en CLP
            $dailyRevenue = (float) $ordersStats->daily_revenue;
            $formattedRevenue = '$' . number_format($dailyRevenue, 0, ',', '.');

            return response()->json([
                'success' => true,
                'data' => [
                    'users' => [
                        'total' => (int) $usersStats->total,
                        'active' => (int) $usersStats->active,
                    ],
                    'ingredients' => [
                        'total' => (int) $ingredientsStats->total,
                        'low_stock' => (int) $ingredientsStats->low_stock,
                    ],
                    'orders' => [
                        'today_total' => (int) $ordersStats->today_total,
                        'completed' => (int) $ordersStats->completed,
                        'daily_revenue' => $dailyRevenue,
                        'formatted_revenue' => $formattedRevenue,
                    ],
                    'top_products' => array_map(fn($prod) => [
                        'id' => $prod->id,
                        'name' => $prod->name,
                        'quantity_sold' => (int) $prod->quantity_sold,
                        'total_earned' => (float) $prod->total_earned,
                        'formatted_earned' => '$' . number_format((float) $prod->total_earned, 0, ',', '.'),
                    ], $topProducts),
                    'critical_inventory' => array_map(fn($ing) => [
                        'id' => $ing->id,
                        'name' => $ing->name,
                        'current_stock' => (float) $ing->stock,
                        'threshold' => (float) $ing->low_stock_threshold,
                        'unit' => $ing->unit,
                        'severity' => $ing->severity,
                    ], $criticalInventory),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener estadísticas del dashboard.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
