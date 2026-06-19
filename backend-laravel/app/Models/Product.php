<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Product extends Model
{
    use HasFactory, HasUuids;

    /**
     * Tabla asociada con el modelo.
     *
     * @var string
     */
    protected $table = 'products';

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
        'description',
        'price',
        'category_id',
        'image_url',
        'is_available',
        'preparation_time',
    ];

    /**
     * Casteo de atributos.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'price' => 'decimal:2',
        'is_available' => 'boolean',
        'preparation_time' => 'integer',
    ];

    // =========================================================
    // RELACIONES ELOQUENT
    // =========================================================

    /**
     * Un producto pertenece a una categoría.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    /**
     * Un producto tiene muchos registros de relación con ingredientes (pivot).
     */
    public function recipeIngredients(): HasMany
    {
        return $this->hasMany(RecipeIngredient::class, 'product_id');
    }

    /**
     * Acceso directo a los ingredientes de la receta (Many-to-Many a través del pivot).
     */
    public function ingredients(): BelongsToMany
    {
        return $this->belongsToMany(
            Ingredient::class,
            'recipe_ingredients',
            'product_id',
            'ingredient_id'
        )->withPivot('quantity', 'unit')->withTimestamps();
    }

    // =========================================================
    // ACCESORES DE NEGOCIO
    // =========================================================

    /**
     * Determina si el producto puede prepararse con el stock actual de ingredientes.
     * Itera sobre los ingredientes del pivot y compara stock vs. cantidad requerida.
     *
     * NOTA: Para usar este accesor de forma eficiente, carga la relación
     * `recipeIngredients.ingredient` previamente con Eager Loading.
     */
    public function getCanBePreparedAttribute(): bool
    {
        // Si la relación aún no fue cargada, consideramos disponible para evitar N+1
        if (!$this->relationLoaded('recipeIngredients')) {
            return $this->is_available;
        }

        foreach ($this->recipeIngredients as $ri) {
            if (!$ri->relationLoaded('ingredient')) {
                continue;
            }
            if ((float) $ri->ingredient->stock < (float) $ri->quantity) {
                return false;
            }
        }

        return $this->is_available;
    }
}
