<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory, HasUuids;

    /**
     * Tabla asociada con el modelo.
     *
     * @var string
     */
    protected $table = 'orders';

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
        'table_id',
        'session_id',
        'user_id',
        'status',
        'subtotal',
        'total',
        'notes',
        'paid_at',
    ];

    /**
     * Casteo de atributos.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'subtotal' => 'decimal:2',
        'total' => 'decimal:2',
        'paid_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_PREPARING = 'preparing';
    public const STATUS_READY = 'ready';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_PAID = 'paid';
    public const STATUS_CANCELLED = 'cancelled';

    public const VALID_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_PREPARING,
        self::STATUS_READY,
        self::STATUS_DELIVERED,
        self::STATUS_PAID,
        self::STATUS_CANCELLED,
    ];

    // =========================================================
    // RELACIONES ELOQUENT
    // =========================================================

    /**
     * Una orden pertenece a una mesa.
     */
    public function table(): BelongsTo
    {
        return $this->belongsTo(RestaurantTable::class, 'table_id');
    }

    /**
     * Una orden pertenece a un mesero o chef (usuario).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Una orden tiene muchos ítems detallados.
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'order_id');
    }

    // =========================================================
    // ACCESORES DE NEGOCIO Y HELPERS
    // =========================================================

    /**
     * Obtiene la etiqueta amigable para el estado actual.
     */
    public function getStatusLabelAttribute(): string
    {
        $labels = [
            self::STATUS_PENDING => 'Pendiente',
            self::STATUS_PREPARING => 'Preparando',
            self::STATUS_READY => 'Listo',
            self::STATUS_DELIVERED => 'Entregado',
            self::STATUS_PAID => 'Pagado',
            self::STATUS_CANCELLED => 'Cancelado',
        ];
        return $labels[$this->status] ?? $this->status;
    }

    /**
     * Minutos transcurridos desde que se creó la orden.
     */
    public function getElapsedMinutesAttribute(): int
    {
        if (!$this->created_at) {
            return 0;
        }
        return (int) floor($this->created_at->diffInMinutes(now()));
    }

    /**
     * Clase CSS de color de temporizador según retraso.
     */
    public function getTimerClassAttribute(): string
    {
        $mins = $this->elapsed_minutes;
        if ($mins > 20) {
            return 'red';
        }
        if ($mins >= 11) {
            return 'yellow';
        }
        return 'green';
    }

    /**
     * Calcula los totales sumando sus ítems.
     */
    public function calculateTotals(): float
    {
        $subtotal = 0.0;
        foreach ($this->items as $item) {
            $subtotal += (float) $item->unit_price * (int) $item->quantity;
        }
        $this->subtotal = $subtotal;
        $this->total = $subtotal;
        return $subtotal;
    }
}
