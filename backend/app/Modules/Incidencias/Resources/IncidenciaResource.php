<?php

namespace App\Modules\Incidencias\Resources;

use App\Modules\Incidencias\Models\Incidencia;
use App\Shared\Enums\Rol;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Incidencia
 */
class IncidenciaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'pedido_id' => $this->pedido_id,
            'tipo_incidencia_id' => $this->tipo_incidencia_id,
            'descripcion' => $this->descripcion,
            'ruta_foto' => $this->ruta_foto,
            'foto_url' => $this->ruta_foto === null ? null : asset('storage/'.$this->ruta_foto),
            'estado' => $this->estado,
            'prioridad' => $this->prioridad,
            'resolucion' => $this->resolucion,
            // La nota para el comercio no se le muestra al repartidor (INT-20).
            'nota_comercio' => $this->when($request->user()?->rol !== Rol::Repartidor, $this->nota_comercio),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'pedido' => $this->whenLoaded('pedido', fn (): array => [
                'id' => $this->pedido->id,
                'numero' => $this->pedido->numero,
            ]),
            'tipo' => $this->whenLoaded('tipo', fn (): array => [
                'id' => $this->tipo->id,
                'nombre' => $this->tipo->nombre,
            ]),
            'usuario' => $this->whenLoaded('usuario', fn (): array => [
                'id' => $this->usuario->id,
                'nombre' => $this->usuario->nombre,
            ]),
        ];
    }
}
