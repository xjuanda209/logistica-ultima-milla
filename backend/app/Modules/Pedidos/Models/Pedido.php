<?php

namespace App\Modules\Pedidos\Models;

use App\Modules\Administracion\Models\Comercio;
use App\Modules\Administracion\Models\Usuario;
use App\Modules\Administracion\Models\Zona;
use App\Modules\Despacho\Models\Evidencia;
use App\Modules\Incidencias\Models\Incidencia;
use App\Shared\Enums\EstadoPedido;
use App\Shared\Enums\Prioridad;
use App\Shared\Enums\Rol;
use Database\Factories\PedidoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'numero', 'comercio_id', 'cliente_id', 'repartidor_id', 'creado_por', 'estado', 'prioridad',
    'direccion', 'barrio', 'indicaciones', 'zona_id', 'franja_inicio', 'franja_fin', 'observaciones',
])]
#[UseFactory(PedidoFactory::class)]
class Pedido extends Model
{
    /** @use HasFactory<PedidoFactory> */
    use HasFactory;

    protected $table = 'pedidos';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'estado' => EstadoPedido::class,
            'prioridad' => Prioridad::class,
            'franja_inicio' => 'datetime',
            'franja_fin' => 'datetime',
        ];
    }

    /**
     * Regla central de visibilidad: qué pedidos puede ver cada rol.
     * - administrador y operador: todos.
     * - comercio: solo los de su comercio.
     * - repartidor: solo los que tiene asignados.
     *
     * Uso: Pedido::query()->visiblesPara($usuario)->get();
     *
     * @param  Builder<Pedido>  $query
     */
    public function scopeVisiblesPara(Builder $query, Usuario $usuario): void
    {
        match ($usuario->rol) {
            Rol::Administrador, Rol::Operador => null,
            Rol::Comercio => $query->where('pedidos.comercio_id', $usuario->comercio_id ?? 0),
            Rol::Repartidor => $query->where('pedidos.repartidor_id', $usuario->id),
        };
    }

    /**
     * Número del próximo pedido, con el formato P-000001: el consecutivo más alto que exista más uno.
     * El número es único en la base: si dos personas crean un pedido a la vez, quien lo use de
     * segundo recibe un error de duplicado y debe pedir otro (ver PedidoController::store).
     */
    public static function siguienteNumero(): string
    {
        $ultimo = self::query()->where('numero', 'like', 'P-______')->max('numero');

        return sprintf('P-%06d', (int) substr((string) $ultimo, 2) + 1);
    }

    /**
     * @return BelongsTo<Comercio, $this>
     */
    public function comercio(): BelongsTo
    {
        return $this->belongsTo(Comercio::class);
    }

    /**
     * @return BelongsTo<Cliente, $this>
     */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    /**
     * @return BelongsTo<Usuario, $this>
     */
    public function repartidor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'repartidor_id');
    }

    /**
     * @return BelongsTo<Usuario, $this>
     */
    public function creador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'creado_por');
    }

    /**
     * @return BelongsTo<Zona, $this>
     */
    public function zona(): BelongsTo
    {
        return $this->belongsTo(Zona::class);
    }

    /**
     * @return HasMany<LineaPedido, $this>
     */
    public function lineas(): HasMany
    {
        return $this->hasMany(LineaPedido::class);
    }

    /**
     * @return HasMany<HistorialPedido, $this>
     */
    public function historial(): HasMany
    {
        return $this->hasMany(HistorialPedido::class)->orderBy('created_at')->orderBy('id');
    }

    /**
     * @return HasMany<Evidencia, $this>
     */
    public function evidencias(): HasMany
    {
        return $this->hasMany(Evidencia::class);
    }

    /**
     * @return HasMany<Incidencia, $this>
     */
    public function incidencias(): HasMany
    {
        return $this->hasMany(Incidencia::class);
    }
}
