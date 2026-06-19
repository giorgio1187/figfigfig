<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryLog extends Model
{
    use HasFactory, HasUuids;

    /**
     * Tabla asociada con el modelo.
     *
     * @var string
     */
    protected $table = 'inventory_logs';

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
     * Deshabilitamos el campo updated_at ya que la tabla de logs
     * solo registra fecha de creación de auditoría (created_at).
     */
    public const UPDATED_AT = null;

    /**
     * Atributos asignables de forma masiva.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'ingredient_id',
        'order_id',
        'user_id',
        'action',
        'quantity',
        'stock_before',
        'stock_after',
        'reason',
    ];

    /**
     * Casteo de atributos.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'quantity' => 'decimal:2',
        'stock_before' => 'decimal:2',
        'stock_after' => 'decimal:2',
    ];

    /**
     * Relación con el ingrediente asociado.
     */
    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class, 'ingredient_id');
    }

    /**
     * Relación con el usuario responsable (opcional).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
