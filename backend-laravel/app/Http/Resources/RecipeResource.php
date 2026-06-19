<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * RecipeResource
 *
 * Emula el contrato JSON original del frontend, como si la tabla `recipes`
 * todavía existiera. En realidad, lee las relaciones de `recipe_ingredients`.
 *
 * Salida esperada por el frontend:
 * {
 *   "id": "<product_uuid>",
 *   "product_id": "<product_uuid>",
 *   "product_name": "...",
 *   "category": "...",
 *   "ingredients": [
 *     { "id": "...", "ingredient_id": "...", "name": "...", "quantity": 0.5, "unit": "kg", "has_enough_stock": true }
 *   ],
 *   "can_be_prepared": true
 * }
 *
 * @mixin \App\Models\Product
 */
class RecipeResource extends JsonResource
{
    /**
     * Transforma el recurso (un Product con sus relaciones cargadas)
     * en el formato de "receta" que espera el frontend antiguo.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var \App\Models\Product $this */
        return [
            // El frontend identifica la "receta" por el UUID del producto
            'id' => $this->id,
            'product_id' => $this->id,
            'product_name' => $this->name,
            'category' => $this->whenLoaded('category', fn() => $this->category?->name),

            // Lista de ingredientes de la receta
            'ingredients' => $this->whenLoaded(
                'recipeIngredients',
                fn() => $this->recipeIngredients->map(fn($ri) => [
                    'id' => $ri->id,
                    'ingredient_id' => $ri->ingredient_id,
                    'name' => $ri->relationLoaded('ingredient') ? $ri->ingredient?->name : null,
                    'quantity' => (float) $ri->quantity,
                    'unit' => $ri->unit,
                    'has_enough_stock' => $ri->has_enough_stock,
                ])
            ),

            // Disponibilidad calculada en el modelo Product
            'can_be_prepared' => $this->can_be_prepared,
        ];
    }
}
