<?php

namespace App\Modules\Pedidos\Resources;

use App\Modules\Despacho\Models\Evidencia;
use App\Modules\Incidencias\Models\Incidencia;
use App\Modules\Pedidos\Models\HistorialPedido;
use App\Modules\Pedidos\Models\LineaPedido;
use App\Modules\Pedidos\Models\Pedido;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Sirve para la lista y para el detalle: las relaciones solo aparecen
 * si el controlador las cargó con with()/load().
 *
 * @mixin Pedido
 */
class PedidoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'numero' => $this->numero,
            'estado' => $this->estado,
            'prioridad' => $this->prioridad,
            'direccion' => $this->direccion,
            'barrio' => $this->barrio,
            'indicaciones' => $this->indicaciones,
            'franja_inicio' => $this->franja_inicio,
            'franja_fin' => $this->franja_fin,
            'observaciones' => $this->observaciones,
            'comercio_id' => $this->comercio_id,
            'cliente_id' => $this->cliente_id,
            'repartidor_id' => $this->repartidor_id,
            'zona_id' => $this->zona_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'incidencia_abierta' => $this->whenHas('incidencia_abierta', fn (): bool => (bool) $this->incidencia_abierta),
            'creado_por' => $this->whenLoaded('creador', fn (): ?string => $this->creador?->nombre),
            'comercio' => $this->whenLoaded('comercio', fn (): array => [
                'id' => $this->comercio->id,
                'nombre' => $this->comercio->nombre,
                'direccion_recogida' => $this->comercio->direccion_recogida,
            ]),
            'cliente' => $this->whenLoaded('cliente', fn (): array => [
                'id' => $this->cliente->id,
                'nombre' => $this->cliente->nombre,
                'telefono' => $this->cliente->telefono,
            ]),
            'repartidor' => $this->whenLoaded('repartidor', fn (): ?array => $this->repartidor === null ? null : [
                'id' => $this->repartidor->id,
                'nombre' => $this->repartidor->nombre,
            ]),
            'zona' => $this->whenLoaded('zona', fn (): array => [
                'id' => $this->zona->id,
                'nombre' => $this->zona->nombre,
            ]),
            'lineas' => $this->whenLoaded('lineas', fn () => $this->lineas->map(fn (LineaPedido $linea): array => [
                'id' => $linea->id,
                'producto' => $linea->producto,
                'cantidad' => $linea->cantidad,
            ])),
            'historial' => $this->whenLoaded('historial', fn () => $this->historial->map(fn (HistorialPedido $evento): array => [
                'id' => $evento->id,
                'tipo_evento' => $evento->tipo_evento,
                'estado_anterior' => $evento->estado_anterior,
                'estado_nuevo' => $evento->estado_nuevo,
                'detalle' => $evento->detalle,
                'cambios' => $evento->cambios(),
                'canal' => $evento->canal,
                'usuario' => $evento->usuario?->nombre,
                'created_at' => $evento->created_at,
            ])),
            'incidencias' => $this->whenLoaded('incidencias', fn () => $this->incidencias->map(fn (Incidencia $incidencia): array => [
                'id' => $incidencia->id,
                'estado' => $incidencia->estado,
                'tipo' => $incidencia->tipo?->nombre,
                'descripcion' => $incidencia->descripcion,
                'prioridad' => $incidencia->prioridad,
                'foto_url' => $incidencia->ruta_foto === null ? null : asset('storage/'.$incidencia->ruta_foto),
                'created_at' => $incidencia->created_at,
            ])),
            'evidencias' => $this->whenLoaded('evidencias', fn () => $this->evidencias->map(fn (Evidencia $evidencia): array => [
                'id' => $evidencia->id,
                'tipo' => $evidencia->tipo,
                'url' => asset('storage/'.$evidencia->ruta_archivo),
                'con_ubicacion' => $evidencia->latitud !== null && $evidencia->longitud !== null,
                'created_at' => $evidencia->created_at,
            ])),
        ];
    }
}
