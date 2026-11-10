<?php

namespace App\Modules\Pedidos\Resources;

use App\Modules\Pedidos\Models\Cliente;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Cliente
 */
class ClienteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'comercio_id' => $this->comercio_id,
            'nombre' => $this->nombre,
            'cedula' => $this->cedula,
            'telefono' => $this->telefono,
            'correo' => $this->correo,
            'direccion' => $this->direccion,
            'barrio' => $this->barrio,
            'indicaciones' => $this->indicaciones,
            'zona_id' => $this->zona_id,
            'comercio' => $this->whenLoaded('comercio', fn (): array => [
                'id' => $this->comercio->id,
                'nombre' => $this->comercio->nombre,
            ]),
            'zona' => $this->whenLoaded('zona', fn (): array => [
                'id' => $this->zona->id,
                'nombre' => $this->zona->nombre,
            ]),
        ];
    }
}
