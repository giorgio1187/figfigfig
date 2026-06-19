<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * ProductResource
 *
 * Mantiene el contrato JSON original del frontend, incluyendo
 * la propiedad `recipe` como objeto anidado (emulación de la
 * tabla `recipes` eliminada en el saneamiento).
 *
 * @mixin \App\Models\Product
 */
class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var \App\Models\Product $this */
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'price' => (float) $this->price,
            'formattedPrice' => '$' . number_format((float) $this->price, 0, ',', '.'),
            'image_url' => $this->image_url,
            'is_available' => $this->is_available,
            'preparation_time' => $this->preparation_time,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            'category_id' => $this->category_id,
            'category' => $this->relationLoaded('category') ? $this->category?->name : null,

            // Emulación del objeto "recipe" original del frontend.
            // Los datos vienen de recipe_ingredients + ingredients via Eager Loading.
            'recipe' => $this->whenLoaded(
                'recipeIngredients',
                fn() => [
                    'product_id' => $this->id,
                    'can_be_prepared' => $this->can_be_prepared,
                    'ingredients' => $this->recipeIngredients->map(fn($ri) => [
                        'id' => $ri->id,
                        'ingredient_id' => $ri->ingredient_id,
                        'name' => $ri->relationLoaded('ingredient') ? $ri->ingredient?->name : null,
                        'quantity' => (float) $ri->quantity,
                        'unit' => $ri->unit,
                        'current_stock' => $ri->relationLoaded('ingredient') ? (float) $ri->ingredient?->stock : null,
                        'has_enough_stock' => $ri->has_enough_stock,
                    ]),
                ]
            ),
        ];
    }
}
