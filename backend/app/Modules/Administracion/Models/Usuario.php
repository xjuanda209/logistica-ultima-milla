<?php

namespace App\Modules\Administracion\Models;

use App\Modules\Pedidos\Models\Pedido;
use App\Shared\Enums\Disponibilidad;
use App\Shared\Enums\Rol;
use Database\Factories\UsuarioFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Usuario del sistema. Reemplaza al modelo "User" de Laravel:
 * es el modelo con el que Sanctum autentica (ver config/auth.php).
 */
#[Fillable(['nombre', 'correo', 'password', 'rol', 'activo', 'comercio_id', 'zona_id', 'disponibilidad'])]
#[Hidden(['password'])]
#[UseFactory(UsuarioFactory::class)]
class Usuario extends Authenticatable
{
    /** @use HasFactory<UsuarioFactory> */
    use HasApiTokens, HasFactory;

    protected $table = 'usuarios';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'rol' => Rol::class,
            'activo' => 'boolean',
            'disponibilidad' => Disponibilidad::class,
        ];
    }

    /**
     * Comercio al que pertenece (solo si el rol es comercio).
     *
     * @return BelongsTo<Comercio, $this>
     */
    public function comercio(): BelongsTo
    {
        return $this->belongsTo(Comercio::class);
    }

    /**
     * Zona que cubre (solo si el rol es repartidor).
     *
     * @return BelongsTo<Zona, $this>
     */
    public function zona(): BelongsTo
    {
        return $this->belongsTo(Zona::class);
    }

    /**
     * Pedidos que tiene asignados como repartidor.
     *
     * @return HasMany<Pedido, $this>
     */
    public function pedidosAsignados(): HasMany
    {
        return $this->hasMany(Pedido::class, 'repartidor_id');
    }

    public function tieneRol(Rol ...$roles): bool
    {
        return in_array($this->rol, $roles, true);
    }
}
