<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * TableGroupResource
 *
 * Representa la estructura completa de un grupo de mesas fusionadas,
 * incluyendo el balance financiero de la sesión y la lista de mesas participantes.
 *
 * @mixin \App\Models\TableGroup
 */
class TableGroupResource extends JsonResource
{
    /**
     * El recurso recibe UNA entrada de TableGroup, pero expone
     * el grupo completo (todas las mesas del session_id).
     * La colección de mesas hermanas debe ser pasada como metadata extra.
     *
     * En la práctica, el controlador llama a este Resource pasando el grupo entero.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Calcula el balance total de la sesión sumando órdenes activas (no canceladas)
        $sessionBalance = 0.0;
        $sessionOrders = 0;

        if ($this->relationLoaded('table') && $this->table?->relationLoaded('orders')) {
            foreach ($this->table->orders as $order) {
                if (!in_array($order->status, ['cancelled'])) {
                    $sessionBalance += (float) $order->total;
                    $sessionOrders++;
                }
            }
        }

        return [
            'id' => $this->id,
            'session_id' => $this->session_id,
            'table_id' => $this->table_id,
            'is_main' => $this->is_main,
            'created_at' => $this->created_at?->toISOString(),

            // Información de la mesa de este slot del grupo
            'table' => $this->whenLoaded('table', fn() => [
                'id' => $this->table->id,
                'table_number' => $this->table->table_number,
                'capacity' => $this->table->capacity,
                'status' => $this->table->status,
            ]),

            // Balance financiero calculado para la UI del salón
            'current_session_balance' => $sessionBalance,
            'active_orders_count' => $sessionOrders,
        ];
    }
}
