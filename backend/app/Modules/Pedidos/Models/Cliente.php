<?php

namespace App\Modules\Pedidos\Models;

use App\Modules\Administracion\Models\Comercio;
use App\Modules\Administracion\Models\Usuario;
use App\Modules\Administracion\Models\Zona;
use App\Shared\Enums\Rol;
use Database\Factories\ClienteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['comercio_id', 'nombre', 'cedula', 'telefono', 'correo', 'direccion', 'barrio', 'indicaciones', 'zona_id'])]
#[UseFactory(ClienteFactory::class)]
class Cliente extends Model
{
    /** @use HasFactory<ClienteFactory> */
    use HasFactory;

    protected $table = 'clientes';

    /**
     * Qué clientes puede ver cada rol: administrador y operador, todos; el comercio, solo los suyos;
     * el repartidor, ninguno. Uso: Cliente::query()->visiblesPara($usuario)->get();
     *
     * @param  Builder<Cliente>  $query
     */
    public function scopeVisiblesPara(Builder $query, Usuario $usuario): void
    {
        match ($usuario->rol) {
            Rol::Administrador, Rol::Operador => null,
            Rol::Comercio => $query->where('clientes.comercio_id', $usuario->comercio_id ?? 0),
            Rol::Repartidor => $query->whereRaw('1 = 0'),
        };
    }

    /**
     * @return BelongsTo<Comercio, $this>
     */
    public function comercio(): BelongsTo
    {
        return $this->belongsTo(Comercio::class);
    }

    /**
     * @return BelongsTo<Zona, $this>
     */
    public function zona(): BelongsTo
    {
        return $this->belongsTo(Zona::class);
    }

    /**
     * @return HasMany<Pedido, $this>
     */
    public function pedidos(): HasMany
    {
        return $this->hasMany(Pedido::class);
    }
}
