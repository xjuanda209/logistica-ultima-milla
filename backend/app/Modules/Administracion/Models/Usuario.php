<?php

namespace App\Modules\Administracion\Models;

use App\Modules\Pedidos\Models\Pedido;
use App\Shared\Enums\Disponibilidad;
use App\Shared\Enums\EstadoPedido;
use App\Shared\Enums\MotivoNoElegible;
use App\Shared\Enums\Rol;
use Database\Factories\UsuarioFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
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

    /**
     * Agrega "pedidos_activos": cuántos pedidos tiene el repartidor en asignado, recogido o en ruta.
     *
     * @param  Builder<Usuario>  $query
     */
    public function scopeConPedidosActivos(Builder $query): void
    {
        $query->withCount([
            'pedidosAsignados as pedidos_activos' => fn ($pedidos) => $pedidos->whereIn('estado', EstadoPedido::activos()),
        ]);
    }

    /**
     * Regla central de asignación: por qué este repartidor NO puede recibir el pedido, o null si
     * puede. Debe estar activo y disponible, ser de la zona del pedido y tener menos pedidos
     * activos que el máximo; tampoco se le reasigna el pedido que ya tiene.
     */
    public function motivoParaNoRecibir(Pedido $pedido, int $pedidosActivos, int $maximo): ?MotivoNoElegible
    {
        return match (true) {
            $pedido->repartidor_id === $this->id && $pedido->estado === EstadoPedido::Asignado => MotivoNoElegible::YaAsignado,
            ! $this->activo => MotivoNoElegible::Desactivado,
            $this->disponibilidad !== Disponibilidad::Disponible => MotivoNoElegible::NoDisponible,
            $this->zona_id !== $pedido->zona_id => MotivoNoElegible::FueraDeZona,
            $pedidosActivos >= $maximo => MotivoNoElegible::MaximoAlcanzado,
            default => null,
        };
    }

    public function tieneRol(Rol ...$roles): bool
    {
        return in_array($this->rol, $roles, true);
    }
}
