<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Ingredient extends Model
{
    use HasFactory, HasUuids;

    /**
     * Tabla asociada con el modelo.
     *
     * @var string
     */
    protected $table = 'ingredients';

    /**
     * Tipo de clave primaria.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Indica si la clave primaria es auto-incremental.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * Atributos asignables de forma masiva.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'stock',
        'unit',
        'low_stock_threshold',
    ];

    /**
     * Casteo de atributos.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'stock' => 'decimal:2',
        'low_stock_threshold' => 'decimal:2',
    ];

    /**
     * Accesores calculados (para compatibilidad de JS y lógica de negocio).
     */

    /**
     * Accesor para verificar si está bajo en stock.
     * Atributo: is_low_stock (para Eloquent) / isLowStock (para el Resource/JS)
     */
    public function getIsLowStockAttribute(): bool
    {
        return (float) $this->stock < (float) $this->low_stock_threshold;
    }

    /**
     * Accesor para el estado textual del stock.
     */
    public function getStockStatusAttribute(): string
    {
        return $this->is_low_stock ? 'Crítico' : 'Óptimo';
    }

    /**
     * Relación con las bitácoras de inventario.
     */
    public function logs(): HasMany
    {
        return $this->hasMany(InventoryLog::class, 'ingredient_id');
    }

    /**
     * Registra un movimiento de inventario en la bitácora de auditoría.
     *
     * @param string $action Tipo de acción ('add', 'remove', 'adjust', 'expired')
     * @param float $quantity Cantidad alterada
     * @param float $stockBefore Stock anterior al movimiento
     * @param float $stockAfter Stock resultante
     * @param string|null $orderId UUID del pedido asociado
     * @param string|null $userId UUID del usuario responsable
     * @param string|null $reason Razón del movimiento
     * @return \App\Models\InventoryLog
     */
    public function logMovement(
        string $action,
        float $quantity,
        float $stockBefore,
        float $stockAfter,
        ?string $orderId = null,
        ?string $userId = null,
        ?string $reason = null
    ): InventoryLog {
        return InventoryLog::create([
            'ingredient_id' => $this->id,
            'order_id' => $orderId,
            'user_id' => $userId,
            'action' => $action,
            'quantity' => $quantity,
            'stock_before' => $stockBefore,
            'stock_after' => $stockAfter,
            'reason' => $reason,
        ]);
    }
}
