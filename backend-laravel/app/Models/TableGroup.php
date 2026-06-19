<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modelo del pivot extendido post-saneamiento de table_groups.
 *
 * Cada fila representa una mesa participante en un grupo de sesión.
 * La mesa principal (anfitriona) tiene is_main = true.
 * El session_id es compartido por todas las filas del mismo grupo.
 *
 * @property string  $id
 * @property string  $session_id
 * @property string  $table_id
 * @property bool    $is_main
 * @property \Illuminate\Support\Carbon $created_at
 */
class TableGroup extends Model
{
    use HasFactory, HasUuids;

    /**
     * @var string
     */
    protected $table = 'table_groups';

    /**
     * @var string
     */
    protected $keyType = 'string';

    /**
     * @var bool
     */
    public $incrementing = false;

    /**
     * Sin updated_at — la tabla solo tiene created_at según el esquema SQL.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'session_id',
        'table_id',
        'is_main',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'is_main' => 'boolean',
        'created_at' => 'datetime',
    ];

    // =========================================================
    // RELACIONES ELOQUENT
    // =========================================================

    /**
     * Esta entrada de grupo pertenece a una mesa del salón.
     */
    public function table(): BelongsTo
    {
        return $this->belongsTo(RestaurantTable::class, 'table_id');
    }

    // =========================================================
    // HELPERS DE SESIÓN
    // =========================================================

    /**
     * Obtiene todas las entradas de un mismo session_id.
     * Útil para cargar el grupo completo desde cualquier miembro.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, TableGroup>
     */
    public static function getGroupBySession(string $sessionId)
    {
        return static::with('table')
            ->where('session_id', $sessionId)
            ->orderByDesc('is_main')
            ->get();
    }

    /**
     * Elimina todas las entradas de un session_id (libera el grupo completo).
     */
    public static function dissolveSession(string $sessionId): int
    {
        return static::where('session_id', $sessionId)->delete();
    }
}
