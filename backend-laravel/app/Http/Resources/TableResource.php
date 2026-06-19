<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * TableResource
 *
 * Representa una mesa del salón con su estado en tiempo real,
 * grupo de sesión activo y balance financiero inyectado.
 * Diseñado para que el frontend pueda pintar el plano del salón
 * sin necesitar llamadas adicionales.
 *
 * @mixin \App\Models\RestaurantTable
 */
class TableResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var \App\Models\RestaurantTable $this */

        // Calcula balance de órdenes activas en esta mesa
        $balance = 0.0;
        $ordersCount = 0;

        if ($this->relationLoaded('orders')) {
            foreach ($this->orders as $order) {
                if (!in_array($order->status, ['cancelled', 'paid'])) {
                    $balance += (float) $order->total;
                    $ordersCount++;
                }
            }
        }

        return [
            'id' => $this->id,
            'table_number' => $this->table_number,
            'capacity' => $this->capacity,
            'status' => $this->status,
            'position_x' => $this->position_x,
            'position_y' => $this->position_y,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Propiedades calculadas para el plano del salón
            'is_available' => $this->is_available,
            'is_main_table' => $this->is_main_table,

            // Información del grupo de sesión activo (si existe)
            'session_id' => $this->session_id,
            'active_group' => $this->whenLoaded('activeGroup', fn() => $this->activeGroup ? [
                'id' => $this->activeGroup->id,
                'session_id' => $this->activeGroup->session_id,
                'is_main' => $this->activeGroup->is_main,
                'created_at' => $this->activeGroup->created_at?->toISOString(),
            ] : null),

            // Balance financiero en tiempo real para mostrar en el chip de la mesa
            'current_session_balance' => $balance,
            'active_orders_count' => $ordersCount,
        ];
    }
}
