<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreIngredientRequest;
use App\Http\Requests\Inventory\UpdateIngredientRequest;
use App\Http\Requests\Inventory\UpdateStockRequest;
use App\Http\Requests\Inventory\RestockRequest;
use App\Http\Resources\IngredientResource;
use App\Models\Ingredient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class InventoryController extends Controller
{
    /**
     * Obtiene el listado completo de ingredientes.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAll()
    {
        try {
            $ingredients = Ingredient::orderBy('name', 'asc')->get();

            return response()->json([
                'success' => true,
                'data' => IngredientResource::collection($ingredients)
            ]);
        } catch (Exception $e) {
            Log::error("GetAll ingredients exception: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Error al obtener inventario'
            ], 500);
        }
    }

    /**
     * Obtiene un ingrediente por su ID.
     *
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getById(string $id)
    {
        try {
            $ingredient = Ingredient::find($id);

            if (!$ingredient) {
                return response()->json([
                    'success' => false,
                    'error' => 'Ingrediente no encontrado'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => new IngredientResource($ingredient)
            ]);
        } catch (Exception $e) {
            Log::error("GetById ingredient exception: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Error al obtener ingrediente'
            ], 500);
        }
    }

    /**
     * Agrega un nuevo ingrediente al inventario.
     *
     * @param StoreIngredientRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function add(StoreIngredientRequest $request)
    {
        try {
            $ingredient = DB::transaction(function () use ($request) {
                $ing = Ingredient::create([
                    'name' => $request->name,
                    'stock' => $request->stock ?? 0,
                    'unit' => $request->unit ?? 'u.',
                    'low_stock_threshold' => $request->low_stock_threshold ?? 10,
                ]);

                // Si se crea con stock inicial mayor a 0, registramos movimiento en la bitácora
                if ((float) $ing->stock > 0) {
                    $ing->logMovement(
                        'adjust',
                        (float) $ing->stock,
                        0.0,
                        (float) $ing->stock,
                        null,
                        null,
                        'Registro e ingreso inicial de ingrediente'
                    );
                }

                return $ing;
            });

            return response()->json([
                'success' => true,
                'data' => new IngredientResource($ingredient)
            ]);
        } catch (Exception $e) {
            Log::error("Add ingredient exception: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Error al agregar ingrediente'
            ], 500);
        }
    }

    /**
     * Actualiza los datos generales de un ingrediente.
     *
     * @param UpdateIngredientRequest $request
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(UpdateIngredientRequest $request, string $id)
    {
        try {
            $ingredient = Ingredient::find($id);

            if (!$ingredient) {
                return response()->json([
                    'success' => false,
                    'error' => 'Ingrediente no encontrado'
                ], 404);
            }

            DB::transaction(function () use ($request, $ingredient) {
                $stockBefore = (float) $ingredient->stock;
                
                $ingredient->update($request->validated());

                // Si en el PUT se modificó directamente el stock, registramos auditoría
                if ($request->has('stock') && (float) $request->stock !== $stockBefore) {
                    $ingredient->logMovement(
                        'adjust',
                        abs((float) $request->stock - $stockBefore),
                        $stockBefore,
                        (float) $request->stock,
                        null,
                        null,
                        'Actualización directa de stock mediante PUT'
                    );
                }
            });

            return response()->json([
                'success' => true,
                'data' => new IngredientResource($ingredient)
            ]);
        } catch (Exception $e) {
            Log::error("Update ingredient exception: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Error al actualizar ingrediente'
            ], 500);
        }
    }

    /**
     * Elimina un ingrediente del inventario.
     *
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function delete(string $id)
    {
        try {
            $ingredient = Ingredient::find($id);

            if (!$ingredient) {
                return response()->json([
                    'success' => false,
                    'error' => 'Ingrediente no encontrado'
                ], 404);
            }

            $ingredient->delete();

            return response()->json([
                'success' => true,
                'message' => 'Ingrediente eliminado'
            ]);
        } catch (Exception $e) {
            Log::error("Delete ingredient exception: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Error al eliminar ingrediente'
            ], 500);
        }
    }

    /**
     * Modifica el stock de manera absoluta (ajuste manual).
     *
     * @param UpdateStockRequest $request
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateStock(UpdateStockRequest $request, string $id)
    {
        try {
            $ingredient = Ingredient::find($id);

            if (!$ingredient) {
                return response()->json([
                    'success' => false,
                    'error' => 'Ingrediente no encontrado'
                ], 404);
            }

            $updated = DB::transaction(function () use ($request, $ingredient) {
                $stockBefore = (float) $ingredient->stock;
                $newStock = (float) $request->stock;

                $ingredient->stock = $newStock;
                $ingredient->save();

                $ingredient->logMovement(
                    'adjust',
                    abs($newStock - $stockBefore),
                    $stockBefore,
                    $newStock,
                    null,
                    $request->userId,
                    $request->reason ?? 'Ajuste manual de stock'
                );

                return $ingredient;
            });

            return response()->json([
                'success' => true,
                'data' => new IngredientResource($updated)
            ]);
        } catch (Exception $e) {
            Log::error("UpdateStock exception: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Error al actualizar stock'
            ], 500);
        }
    }

    /**
     * Añade cantidad de forma incremental al stock (reabastecimiento).
     *
     * @param RestockRequest $request
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function restock(RestockRequest $request, string $id)
    {
        try {
            $ingredient = Ingredient::find($id);

            if (!$ingredient) {
                return response()->json([
                    'success' => false,
                    'error' => 'Ingrediente no encontrado'
                ], 404);
            }

            $updated = DB::transaction(function () use ($request, $ingredient) {
                $stockBefore = (float) $ingredient->stock;
                $quantityToAdd = (float) $request->quantity;
                $newStock = $stockBefore + $quantityToAdd;

                $ingredient->stock = $newStock;
                $ingredient->save();

                $ingredient->logMovement(
                    'add',
                    $quantityToAdd,
                    $stockBefore,
                    $newStock,
                    null,
                    $request->userId,
                    $request->reason ?? 'Reabastecimiento manual'
                );

                return $ingredient;
            });

            return response()->json([
                'success' => true,
                'data' => new IngredientResource($updated)
            ]);
        } catch (Exception $e) {
            Log::error("Restock exception: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Error al reabastecer stock'
            ], 500);
        }
    }

    /**
     * Obtiene alertas de ingredientes con niveles de stock crítico.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getLowStockAlerts()
    {
        try {
            // Buscamos los insumos cuyo stock sea menor al umbral
            $alerts = Ingredient::whereColumn('stock', '<', 'low_stock_threshold')
                ->orderBy('stock', 'asc')
                ->get()
                ->map(function ($ing) {
                    $stock = (float) $ing->stock;
                    $threshold = (float) $ing->low_stock_threshold;
                    
                    // Nivel de gravedad idéntico al de la vista low_stock_alerts
                    $severity = ($stock === 0.0 || $stock < $threshold * 0.5) ? 'critical' : 'warning';

                    return [
                        'id' => $ing->id,
                        'name' => $ing->name,
                        'current_stock' => $stock,
                        'threshold' => $threshold,
                        'unit' => $ing->unit,
                        'severity' => $severity
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $alerts
            ]);
        } catch (Exception $e) {
            Log::error("GetLowStockAlerts exception: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Error al obtener alertas'
            ], 500);
        }
    }

    /**
     * Verifica la disponibilidad de insumos para elaborar un producto específico.
     *
     * @param string $productId
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkProductAvailability(string $productId)
    {
        try {
            // Consulta directa optimizada tras la remoción de la tabla 'recipes'.
            // recipe_ingredients ahora tiene product_id directo.
            $recipeItems = DB::table('recipe_ingredients as ri')
                ->join('ingredients as i', 'ri.ingredient_id', '=', 'i.id')
                ->where('ri.product_id', $productId)
                ->select(
                    'ri.quantity_required',
                    'i.id as ingredient_id',
                    'i.name as ingredient_name',
                    'i.stock as available_stock'
                )
                ->get();

            if ($recipeItems->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'available' => true,
                        'reason' => 'Producto sin receta definida'
                    ]
                ]);
            }

            $missingIngredients = [];

            foreach ($recipeItems as $item) {
                $available = (float) $item->available_stock;
                $required = (float) $item->quantity_required;

                if ($available < $required) {
                    $missingIngredients[] = [
                        'ingredient_id' => $item->ingredient_id,
                        'name' => $item->ingredient_name,
                        'required' => $required,
                        'available' => $available
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'available' => empty($missingIngredients),
                    'missing_ingredients' => $missingIngredients
                ]
            ]);
        } catch (Exception $e) {
            Log::error("CheckProductAvailability exception: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Error al verificar disponibilidad'
            ], 500);
        }
    }
}
