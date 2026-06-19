<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class RestaurantTable extends Model
{
    use HasFactory, HasUuids;

    /**
     * Tabla de la base de datos.
     *
     * @var string
     */
    protected $table = 'restaurant_tables';

    /**
     * @var string
     */
    protected $keyType = 'string';

    /**
     * @var bool
     */
    public $incrementing = false;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'table_number',
        'capacity',
        'status',
        'position_x',
        'position_y',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'capacity' => 'integer',
        'position_x' => 'integer',
        'position_y' => 'integer',
    ];

    /** Valores válidos de estado sincronizados con el CHECK de PostgreSQL. */
    public const STATUS_AVAILABLE = 'available';
    public const STATUS_OCCUPIED = 'occupied';
    public const STATUS_RESERVED = 'reserved';
    public const STATUS_MAINTENANCE = 'maintenance';

    // =========================================================
    // RELACIONES ELOQUENT
    // =========================================================

    /**
     * Una mesa puede pertenecer a múltiples grupos de sesión (historial).
     * La relación activa se filtra en el Resource con whereNull/session activa.
     */
    public function tableGroups(): HasMany
    {
        return $this->hasMany(TableGroup::class, 'table_id');
    }

    /**
     * El grupo de sesión activo de esta mesa (el más reciente).
     * Útil para Eager Loading liviano al pintar el plano del salón.
     */
    public function activeGroup(): HasOne
    {
        return $this->hasOne(TableGroup::class, 'table_id')->latest('created_at');
    }

    /**
     * Las órdenes asociadas a esta mesa.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'table_id');
    }

    // =========================================================
    // ACCESORES DE NEGOCIO
    // =========================================================

    /**
     * Verdadero si la mesa está libre para sentar nuevos clientes.
     */
    public function getIsAvailableAttribute(): bool
    {
        return $this->status === self::STATUS_AVAILABLE;
    }

    /**
     * Devuelve el session_id activo si la mesa forma parte de un grupo.
     * Requiere que la relación `activeGroup` esté pre-cargada.
     */
    public function getSessionIdAttribute(): ?string
    {
        if ($this->relationLoaded('activeGroup') && $this->activeGroup) {
            return $this->activeGroup->session_id;
        }
        return null;
    }

    /**
     * Verdadero si la mesa es la mesa principal (anfitriona) del grupo fusionado.
     * Requiere que la relación `activeGroup` esté pre-cargada.
     */
    public function getIsMainTableAttribute(): bool
    {
        if ($this->relationLoaded('activeGroup') && $this->activeGroup) {
            return (bool) $this->activeGroup->is_main;
        }
        return false;
    }
}
