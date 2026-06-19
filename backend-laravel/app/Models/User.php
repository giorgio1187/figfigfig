<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasUuids;

    /**
     * Tabla asociada con el modelo.
     *
     * @var string
     */
    protected $table = 'users';

    /**
     * El tipo de clave primaria.
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
     * Los atributos que son asignables de forma masiva.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'username',
        'password',
        'name',
        'role',
        'is_active',
        'station',
    ];

    /**
     * Los atributos que deben ocultarse para la serialización.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Los atributos que deben ser casteados a tipos específicos.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'password' => 'hashed',
    ];

    /**
     * Constante con los roles válidos del sistema.
     */
    public const VALID_ROLES = ['admin', 'waiter', 'chef'];

    /**
     * Mutador para asegurar que el rol sea siempre válido antes de guardar.
     */
    public function setRoleAttribute(string $value): void
    {
        $role = strtolower($value);
        if (!in_array($role, self::VALID_ROLES)) {
            throw new \InvalidArgumentException("El rol '{$value}' no es válido para el sistema FIG.");
        }
        $this->attributes['role'] = $role;
    }

    /**
     * Helper para verificar si el usuario es administrador.
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Helper para verificar si el usuario es mesero (waiter).
     */
    public function isWaiter(): bool
    {
        return $this->role === 'waiter';
    }

    /**
     * Helper para verificar si el usuario es chef.
     */
    public function isChef(): bool
    {
        return $this->role === 'chef';
    }
}
