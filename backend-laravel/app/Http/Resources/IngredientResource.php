<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IngredientResource extends JsonResource
{
    /**
     * Desactiva el envoltorio 'data' para este recurso.
     */
    public static $wrap = null;

    /**
     * Transforma el recurso en un array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'stock' => (float) $this->stock,
            'unit' => $this->unit,
            'low_stock_threshold' => (float) $this->low_stock_threshold,
            'isLowStock' => $this->is_low_stock,
            'stockStatus' => $this->stock_status,
        ];
    }
}
