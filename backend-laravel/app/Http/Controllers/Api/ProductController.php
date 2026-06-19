<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * GET /api/products
     *
     * Devuelve el catálogo completo con categoría y receta anidadas.
     * Aplica filtros opcionales: category_id, is_available, search.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Product::with([
            'category',
            'recipeIngredients.ingredient',
        ]);

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->query('category_id'));
        }

        if ($request->has('is_available')) {
            $query->where('is_available', filter_var($request->query('is_available'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('search')) {
            $term = '%' . $request->query('search') . '%';
            $query->where('name', 'ilike', $term);
        }

        $products = $query->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'data' => ProductResource::collection($products),
        ]);
    }

    /**
     * GET /api/products/{id}
     *
     * Devuelve un producto individual con su categoría y receta.
     */
    public function show(string $id): JsonResponse
    {
        $product = Product::with([
            'category',
            'recipeIngredients.ingredient',
        ])->find($id);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Producto no encontrado.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new ProductResource($product),
        ]);
    }

    /**
     * GET /api/products/categories
     *
     * Devuelve todas las categorías disponibles para los filtros del menú.
     */
    public function categories(): JsonResponse
    {
        $categories = Category::orderBy('name')->get(['id', 'name']);

        return response()->json([
            'success' => true,
            'data' => $categories,
        ]);
    }
}
