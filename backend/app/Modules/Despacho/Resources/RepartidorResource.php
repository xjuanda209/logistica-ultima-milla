<?php

namespace App\Modules\Despacho\Resources;

use App\Modules\Administracion\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un repartidor con su carga ("1 / 3" = pedidos_activos / max_pedidos_activos).
 * El controlador carga la zona y pone pedidos_activos (Usuario::scopeConPedidosActivos) y
 * max_pedidos_activos. "elegible" y "motivo" solo van en la lista de candidatos de un pedido (INT-09).
 *
 * @mixin Usuario
 */
class RepartidorResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'correo' => $this->correo,
            'activo' => $this->activo,
            'disponibilidad' => $this->disponibilidad,
            'zona_id' => $this->zona_id,
            'zona' => $this->whenLoaded('zona', fn (): ?array => $this->zona === null ? null : [
                'id' => $this->zona->id,
                'nombre' => $this->zona->nombre,
            ]),
            'pedidos_activos' => (int) $this->pedidos_activos,
            'max_pedidos_activos' => (int) $this->max_pedidos_activos,
            'elegible' => $this->whenHas('motivo', fn (): bool => $this->motivo === null),
            'motivo' => $this->whenHas('motivo'),
        ];
    }
}
