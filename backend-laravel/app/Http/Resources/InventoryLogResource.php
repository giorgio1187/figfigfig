<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryLogResource extends JsonResource
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
            'ingredient_id' => $this->ingredient_id,
            'order_id' => $this->order_id,
            'user_id' => $this->user_id,
            'action' => $this->action,
            'quantity' => (float) $this->quantity,
            'stock_before' => (float) $this->stock_before,
            'stock_after' => (float) $this->stock_after,
            'reason' => $this->reason,
            'created_at' => $this->created_at ? $this->created_at->toISOString() : null,
            'ingredient_name' => $this->ingredient_name ?? ($this->ingredient ? $this->ingredient->name : 'Desconocido'),
            'user_name' => $this->user_name ?? ($this->user ? $this->user->name : 'Sistema'),
        ];
    }
}
