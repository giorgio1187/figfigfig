<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TableGroupResource;
use App\Http\Resources\TableResource;
use App\Models\RestaurantTable;
use App\Models\TableGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class TableController extends Controller
{
    // =========================================================
    // LECTURA DEL SALÓN
    // =========================================================

    /**
     * GET /api/tables
     *
     * Devuelve todas las mesas del salón con su estado en tiempo real,
     * grupo activo y balance de órdenes abiertas.
     * Aplica filtro opcional por `status`.
     */
    public function getAll(Request $request): JsonResponse
    {
        return cache()->remember('tables_status', 2, function () {
            // Usamos el modelo Eloquent para mantener compatibilidad con TableResource pero sin relaciones
            $tables = \App\Models\RestaurantTable::orderByRaw('CAST(table_number AS INTEGER) ASC')->get();
            $groups = \DB::table('table_groups')->get()->groupBy('table_id');

            $mappedTables = $tables->map(function ($table) use ($groups) {
                $group = $groups->get($table->id)?->first();
                // Seteamos la relación de forma limpia para el Resource
                $table->setRelation('activeGroup', $group ? new \App\Models\TableGroup((array) $group) : null);
                return $table;
            });

            return response()->json([
                'success' => true,
                'data' => \App\Http\Resources\TableResource::collection($mappedTables)
            ]);
        });
    }

    /**
     * GET /api/tables/available
     *
     * Listado ligero de mesas disponibles (para el selector de asignación de pedidos).
     */
    public function getAvailable(): JsonResponse
    {
        $tables = RestaurantTable::where('status', RestaurantTable::STATUS_AVAILABLE)
            ->orderBy('table_number')
            ->get(['id', 'table_number', 'capacity', 'status']);

        return response()->json([
            'success' => true,
            'data' => $tables,
        ]);
    }

    /**
     * GET /api/tables/{id}
     *
     * Detalle completo de una mesa individual.
     */
    public function getById(string $id): JsonResponse
    {
        $table = RestaurantTable::with([
            'activeGroup',
            'orders' => fn($q) => $q->whereNotIn('status', ['cancelled', 'paid']),
        ])->find($id);

        if (!$table) {
            return response()->json([
                'success' => false,
                'message' => 'Mesa no encontrada.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new TableResource($table),
        ]);
    }

    /**
     * GET /api/tables/group/{sessionId}
     *
     * Devuelve todos los slots (mesas) que comparten un mismo session_id.
     */
    public function getTableGroup(string $sessionId): JsonResponse
    {
        $group = TableGroup::getGroupBySession($sessionId);

        if ($group->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Grupo de sesión no encontrado.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => TableGroupResource::collection($group),
        ]);
    }

    // =========================================================
    // GESTIÓN CRUD DE MESAS
    // =========================================================

    /**
     * POST /api/tables
     *
     * Crea una nueva mesa en el plano del salón utilizando parámetros manuales.
     */
    public function create(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'table_number' => ['required', 'integer', 'min:1', 'unique:restaurant_tables,table_number'],
            'capacity' => ['sometimes', 'integer', 'min:1', 'max:30'],
            'position_x' => ['sometimes', 'integer'],
            'position_y' => ['sometimes', 'integer'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos de la mesa inválidos.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $table = RestaurantTable::create([
            'table_number' => $request->table_number,
            'capacity' => $request->input('capacity', 4),
            'status' => RestaurantTable::STATUS_AVAILABLE,
            'position_x' => $request->input('position_x'),
            'position_y' => $request->input('position_y'),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Mesa #{$table->table_number} creada exitosamente.",
            'data' => new TableResource($table),
        ], 201);
    }

    /**
     * PUT /api/tables/{id}
     *
     * Actualiza los datos de configuración de una mesa (capacidad, posición).
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $table = RestaurantTable::find($id);

        if (!$table) {
            return response()->json(['success' => false, 'message' => 'Mesa no encontrada.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'table_number' => ['sometimes', 'integer', 'min:1', "unique:restaurant_tables,table_number,{$id}"],
            'capacity' => ['sometimes', 'integer', 'min:1', 'max:30'],
            'position_x' => ['sometimes', 'integer'],
            'position_y' => ['sometimes', 'integer'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos inválidos.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $table->update($request->only(['table_number', 'capacity', 'position_x', 'position_y']));

        return response()->json([
            'success' => true,
            'message' => 'Mesa actualizada.',
            'data' => new TableResource($table->fresh(['activeGroup'])),
        ]);
    }

    /**
     * PATCH /api/tables/{id}/status
     *
     * Cambia el estado operativo de una mesa (available, occupied, reserved, maintenance).
     */
    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $table = RestaurantTable::find($id);

        if (!$table) {
            return response()->json(['success' => false, 'message' => 'Mesa no encontrada.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => ['required', 'string', 'in:available,occupied,reserved,maintenance'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Estado inválido.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $table->update(['status' => $request->status]);

        return response()->json([
            'success' => true,
            'message' => "Estado de mesa #{$table->table_number} actualizado a '{$request->status}'.",
            'data' => new TableResource($table->fresh(['activeGroup'])),
        ]);
    }

    /**
     * PATCH /api/tables/{id}/edit
     *
     * Alias para actualización parcial de posición y capacidad desde el editor del plano.
     */
    public function editTable(Request $request, string $id): JsonResponse
    {
        return $this->update($request, $id);
    }

    /**
     * DELETE /api/tables/{id}
     *
     * Elimina una mesa del plano. Solo se permite si está disponible y sin grupo activo.
     */
    public function delete(string $id): JsonResponse
    {
        $table = RestaurantTable::with('activeGroup')->find($id);

        if (!$table) {
            return response()->json(['success' => false, 'message' => 'Mesa no encontrada.'], 404);
        }

        if ($table->status !== RestaurantTable::STATUS_AVAILABLE) {
            return response()->json([
                'success' => false,
                'message' => "No se puede eliminar la mesa #{$table->table_number} porque está en estado '{$table->status}'.",
            ], 409);
        }

        if ($table->activeGroup) {
            return response()->json([
                'success' => false,
                'message' => "La mesa #{$table->table_number} pertenece a un grupo activo. Desfusiónela primero.",
            ], 409);
        }

        $tableNumber = $table->table_number;
        $table->delete();

        return response()->json([
            'success' => true,
            'message' => "Mesa #{$tableNumber} eliminada del salón.",
        ]);
    }

    // =========================================================
    // FUSIÓN Y DESFUSIÓN DE MESAS
    // =========================================================

    /**
     * POST /api/tables/merge
     */
    public function mergeTables(Request $request): JsonResponse
    {
        if ($request->has('tableIds') && !$request->has('tables_to_merge')) {
            $request->merge(['tables_to_merge' => $request->tableIds]);
        }
        if ($request->has('table_ids') && !$request->has('tables_to_merge')) {
            $request->merge(['tables_to_merge' => $request->table_ids]);
        }
        if ($request->has('mainTableId') && !$request->has('main_table_id')) {
            $request->merge(['main_table_id' => $request->mainTableId]);
        }

        $mainTableId = $request->main_table_id;
        $tablesToMerge = array_unique($request->tables_to_merge ?? []);

        try {
            DB::beginTransaction();

            $activeGroup = DB::table('table_groups')->where('table_id', $mainTableId)->first();
            $sessionId = $activeGroup ? $activeGroup->session_id : (string) str()->uuid();

            if (!$activeGroup) {
                DB::statement(
                    'INSERT INTO table_groups (id, session_id, table_id, created_at) VALUES (?, ?, ?, ?)',
                    [(string) str()->uuid(), $sessionId, $mainTableId, now()]
                );
            }

            foreach ($tablesToMerge as $tableId) {
                if ($tableId === $mainTableId) continue;

                DB::table('table_groups')->where('table_id', $tableId)->delete();

                DB::statement(
                    'INSERT INTO table_groups (id, session_id, table_id, created_at) VALUES (?, ?, ?, ?)',
                    [(string) str()->uuid(), $sessionId, $tableId, now()]
                );

                DB::table('restaurant_tables')->where('id', $tableId)->update(['status' => 'occupied']);
            }

            DB::table('restaurant_tables')->where('id', $mainTableId)->update(['status' => 'occupied']);

            DB::commit();
            cache()->forget('tables_status');

            return response()->json(['success' => true, 'message' => 'Mesas fusionadas correctamente.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/tables/unmerge
     */
    public function unmergeTable(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'table_id' => ['required', 'uuid', 'exists:restaurant_tables,id'],
            'session_id' => ['sometimes', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos de desfusión inválidos.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $table = RestaurantTable::with('activeGroup')->find($request->table_id);

        if (!$table) {
            return response()->json(['success' => false, 'message' => 'Mesa no encontrada.'], 404);
        }

        $sessionId = $request->input('session_id', $table->session_id);

        if (!$sessionId) {
            return response()->json([
                'success' => false,
                'message' => "La mesa #{$table->table_number} no pertenece a ningún grupo activo.",
            ], 409);
        }

        $groupSlots = TableGroup::where('session_id', $sessionId)->get();

        if ($groupSlots->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => "No se encontró el grupo de sesión '{$sessionId}'.",
            ], 404);
        }

        DB::transaction(function () use ($table, $sessionId, $groupSlots) {
            $removedSlot = $groupSlots->firstWhere('table_id', $table->id);
            $wasMain = $removedSlot?->is_main ?? false;

            TableGroup::where('session_id', $sessionId)
                ->where('table_id', $table->id)
                ->delete();

            $table->update(['status' => RestaurantTable::STATUS_AVAILABLE]);

            $remainingSlots = $groupSlots->where('table_id', '!=', $table->id)->values();

            if ($remainingSlots->isEmpty()) {
                return;
            }

            if ($wasMain) {
                $nextMain = $remainingSlots->first();
                TableGroup::where('session_id', $sessionId)
                    ->where('table_id', $nextMain->table_id)
                    ->update(['is_main' => true]);
            }
        });

        $remainingGroup = TableGroup::getGroupBySession($sessionId);

        return response()->json([
            'success' => true,
            'message' => "Mesa #{$table->table_number} separada del grupo '{$sessionId}' exitosamente.",
            'data' => [
                'unmerged_table' => new TableResource($table->fresh(['activeGroup'])),
                'remaining_group' => $remainingGroup->isNotEmpty()
                    ? TableGroupResource::collection($remainingGroup)
                    : null,
            ],
        ]);
    }

    /**
     * POST /api/tables (Acción por defecto desde el botón rápido de Front)
     * * CORREGIDO: Busca el número de mesa real usando ordenamiento numérico 
     * para evitar el bloqueo cíclico con el número "10".
     */
    public function store(Request $request): JsonResponse
    {
        try {
            // CORRECCIÓN CLAVE: Forzamos a la base de datos a tratar la columna de texto como INTEGER para ordenar bien
            $lastTable = DB::table('restaurant_tables')
                ->orderByRaw('CAST(table_number AS INTEGER) DESC')
                ->first();

            $nextNumber = $lastTable ? ((int)$lastTable->table_number + 1) : 1;
            $newId = (string) str()->uuid();

            DB::table('restaurant_tables')->insert([
                'id' => $newId,
                'table_number' => (string)$nextNumber,
                'capacity' => 4, // Capacidad estándar asignada por defecto
                'status' => 'available',
                'created_at' => now(),
                'updated_at' => now()
            ]);

            cache()->forget('tables_status'); // Limpiamos la caché del salón

            return response()->json([
                'success' => true,
                'message' => "Mesa {$nextNumber} creada con éxito.",
                'data' => [
                    'id' => $newId,
                    'table_number' => (string)$nextNumber,
                    'status' => 'available'
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}