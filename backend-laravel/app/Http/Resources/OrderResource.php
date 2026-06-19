<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * OrderResource
 *
 * Mantiene la compatibilidad 1:1 con el contrato JSON esperado por la SPA frontend.
 *
 * @mixin \App\Models\Order
 */
class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var \App\Models\Order $this */

        // Formateador CLP para total y subtotal
        $totalFormatted = '$' . number_format((float) $this->total, 0, ',', '.');

        return [
            'id' => $this->id,
            'table_id' => $this->table_id,
            'table_number' => $this->relationLoaded('table') ? $this->table?->table_number : ($this->table?->table_number ?? null),
            'session_id' => $this->session_id,
            'user_id' => $this->user_id,
            'status' => $this->status,
            'statusLabel' => $this->status_label,
            'subtotal' => (float) $this->subtotal,
            'total' => (float) $this->total,
            'totalFormatted' => $totalFormatted,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'paid_at' => $this->paid_at?->toISOString(),
            'elapsedMinutes' => $this->elapsed_minutes,
            'minutes_pending' => $this->elapsed_minutes,
            'timerClass' => $this->timer_class,

            // Relación opcional de mesa cargada
            'table' => $this->whenLoaded('table', fn() => [
                'id' => $this->table?->id,
                'table_number' => $this->table?->table_number,
                'capacity' => $this->table?->capacity,
                'status' => $this->table?->status,
            ]),

            // Relación opcional de usuario responsable
            'user' => $this->whenLoaded('user', fn() => [
                'id' => $this->user?->id,
                'username' => $this->user?->username,
                'name' => $this->user?->name,
                'role' => $this->user?->role,
            ]),

            // Relación cargada de ítems estructurada según el contrato exacto del frontend
            'items' => $this->whenLoaded('items', fn() => $this->items->map(fn($item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->relationLoaded('product') ? ($item->product?->name ?? 'Producto Eliminado') : 'Cargando...',
                'quantity' => (int) $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'subtotal' => (float) $item->subtotal,
                'notes' => $item->notes,
            ])),
        ];
    }
}
