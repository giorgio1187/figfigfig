<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecipeIngredient extends Model
{
    use HasFactory, HasUuids;

    /**
     * Tabla del pivot extendido post-saneamiento.
     * Reemplaza la antigua tabla `recipes` que fue eliminada.
     *
     * @var string
     */
    protected $table = 'recipe_ingredients';

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
        'product_id',
        'ingredient_id',
        'quantity',
        'unit',
    ];

    /**
     * Casteo de atributos.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'quantity' => 'decimal:3',
    ];

    // =========================================================
    // RELACIONES ELOQUENT
    // =========================================================

    /**
     * Este registro de receta pertenece a un producto.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * Este registro de receta hace referencia a un ingrediente del inventario.
     */
    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class, 'ingredient_id');
    }

    // =========================================================
    // ACCESORES DE NEGOCIO
    // =========================================================

    /**
     * Indica si hay stock suficiente del ingrediente para esta línea de receta.
     * Requiere que la relación `ingredient` esté pre-cargada.
     */
    public function getHasEnoughStockAttribute(): bool
    {
        if (!$this->relationLoaded('ingredient') || !$this->ingredient) {
            return true; // Optimistic: no bloqueamos si no hay datos
        }

        return (float) $this->ingredient->stock >= (float) $this->quantity;
    }
}
