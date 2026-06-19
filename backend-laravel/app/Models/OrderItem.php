<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasFactory, HasUuids;

    /**
     * Tabla asociada con el modelo.
     *
     * @var string
     */
    protected $table = 'order_items';

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
     * Deshabilitamos el campo updated_at ya que la tabla de items de orden
     * solo registra fecha de creación (created_at).
     */
    public const UPDATED_AT = null;

    /**
     * Atributos asignables de forma masiva.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'order_id',
        'product_id',
        'quantity',
        'unit_price',
        'subtotal',
        'notes',
    ];

    /**
     * Casteo de atributos.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    // =========================================================
    // RELACIONES ELOQUENT
    // =========================================================

    /**
     * Un ítem pertenece a una orden.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    /**
     * Un ítem hace referencia a un producto.
     * Puede ser nulo si el producto fue eliminado (ON DELETE SET NULL).
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
