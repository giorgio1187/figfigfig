<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RecipeResource;
use App\Models\Ingredient;
use App\Models\Product;
use App\Models\RecipeIngredient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class RecipeController extends Controller
{
    /**
     * GET /api/recipes
     *
     * Devuelve todos los productos con sus recetas emuladas.
     * Mapea Product → RecipeResource para mantener el contrato del frontend.
     */
    public function index(): JsonResponse
    {
        $products = Product::with([
            'category',
            'recipeIngredients.ingredient',
        ])->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'data' => RecipeResource::collection($products),
        ]);
    }

    /**
     * GET /api/recipes/product/{productId}
     *
     * Devuelve la "receta" de un producto específico.
     */
    public function showByProduct(string $productId): JsonResponse
    {
        $product = Product::with([
            'category',
            'recipeIngredients.ingredient',
        ])->find($productId);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Producto no encontrado.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new RecipeResource($product),
        ]);
    }

    /**
     * POST /api/recipes
     *
     * Crea (o reemplaza) la receta completa de un producto.
     * * MODIFICADO: Ignora filas con cantidad 0 o vacías para evitar conflictos de validación del modal.
     */
    public function store(Request $request): JsonResponse
    {
        // Limpieza previa: Filtramos el array antes de la validación estricta de Laravel
        if ($request->has('ingredients') && is_array($request->ingredients)) {
            $filteredIngredients = [];

            foreach ($request->ingredients as $item) {
                // Soportamos quantity o quantity_required si viene del front
                $quantity = $item['quantity'] ?? $item['quantity_required'] ?? 0;
                $numericQuantity = (float) $quantity;

                // 💡 Si el ingrediente se envía en 0 (no checkeado en el modal), se salta y no se procesa
                if ($numericQuantity <= 0) {
                    continue;
                }

                // Autodetectamos la unidad desde la base de datos si el front no la manda
                $unit = $item['unit'] ?? null;
                if (!$unit && isset($item['ingredient_id'])) {
                    $dbIngredient = DB::table('ingredients')->where('id', $item['ingredient_id'])->first();
                    $unit = $dbIngredient ? $dbIngredient->unit : 'unidades';
                }

                $filteredIngredients[] = [
                    'ingredient_id' => $item['ingredient_id'],
                    'quantity' => $numericQuantity,
                    'unit' => $unit
                ];
            }

            // Sobrescribimos el request con la lista limpia
            $request->merge(['ingredients' => $filteredIngredients]);
        } else {
            $request->merge(['ingredients' => []]);
        }

        // Validación adaptada para tolerar recetas vacías o listas ya filtradas
        $validator = Validator::make($request->all(), [
            'product_id' => ['required', 'uuid', 'exists:products,id'],
            'ingredients' => ['sometimes', 'array'],
            'ingredients.*.ingredient_id' => ['required_with:ingredients', 'uuid', 'exists:ingredients,id'],
            'ingredients.*.quantity' => ['required_with:ingredients', 'numeric', 'min:0.001'],
            'ingredients.*.unit' => ['required_with:ingredients', 'string', 'max:30'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos de receta inválidos.',
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::transaction(function () use ($request) {
            // Elimina todos los ingredientes anteriores del producto (reemplaza la receta)
            RecipeIngredient::where('product_id', $request->product_id)->delete();

            if (!empty($request->ingredients)) {
                $rows = collect($request->ingredients)->map(fn($item) => [
                    'product_id' => $request->product_id,
                    'ingredient_id' => $item['ingredient_id'],
                    'quantity' => $item['quantity'],
                    'unit' => $item['unit'],
                ])->toArray();

                RecipeIngredient::insert($rows);
            }
        });

        $product = Product::with(['category', 'recipeIngredients.ingredient'])
            ->find($request->product_id);

        return response()->json([
            'success' => true,
            'message' => 'Receta guardada exitosamente.',
            'data' => new RecipeResource($product),
        ], 201);
    }

    /**
     * PATCH /api/recipes/{id}/ingredients
     *
     * Añade un único ingrediente a la receta de un producto.
     * El parámetro {id} es el product_id (para mantener compatibilidad con el frontend).
     * Body: { "ingredient_id": "uuid", "quantity": 0.5, "unit": "kg" }
     * * MÓDULO CORREGIDO: Ahora intercepta "quantity_required" y autodetecta la unidad ("unit").
     */
    public function addIngredient(Request $request, string $id): JsonResponse
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Producto no encontrado.',
            ], 404);
        }

        // 1. Si el frontend manda 'quantity_required', lo copiamos a 'quantity'
        if ($request->has('quantity_required') && !$request->has('quantity')) {
            $request->merge(['quantity' => $request->quantity_required]);
        }

        // 2. Si el frontend no manda 'unit', buscamos la unidad real del ingrediente en la BD
        if (!$request->has('unit') && $request->has('ingredient_id')) {
            $ingredient = DB::table('ingredients')->where('id', $request->ingredient_id)->first();
            $request->merge(['unit' => $ingredient ? $ingredient->unit : 'unidades']);
        }

        $validator = Validator::make($request->all(), [
            'ingredient_id' => ['required', 'uuid', 'exists:ingredients,id'],
            'quantity' => ['required', 'numeric', 'min:0.001'],
            'unit' => ['required', 'string', 'max:30'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos del ingrediente inválidos.',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Upsert: si ya existe el ingrediente en la receta, actualiza la cantidad
        RecipeIngredient::updateOrCreate(
            [
                'product_id' => $id,
                'ingredient_id' => $request->ingredient_id,
            ],
            [
                'quantity' => $request->quantity,
                'unit' => $request->unit,
            ]
        );

        $product->load(['category', 'recipeIngredients.ingredient']);

        return response()->json([
            'success' => true,
            'message' => 'Ingrediente añadido a la receta.',
            'data' => new RecipeResource($product),
        ]);
    }

    /**
     * DELETE /api/recipes/{id}/ingredients/{ingredientId}
     *
     * Elimina un ingrediente específico de la receta de un producto.
     * El parámetro {id} es el product_id.
     */
    public function removeIngredient(string $id, string $ingredientId): JsonResponse
    {
        $deleted = RecipeIngredient::where('product_id', $id)
            ->where('ingredient_id', $ingredientId)
            ->delete();

        if (!$deleted) {
            return response()->json([
                'success' => false,
                'message' => 'Ingrediente no encontrado en la receta.',
            ], 404);
        }

        $product = Product::with(['category', 'recipeIngredients.ingredient'])->find($id);

        return response()->json([
            'success' => true,
            'message' => 'Ingrediente eliminado de la receta.',
            'data' => $product ? new RecipeResource($product) : null,
        ]);
    }
}