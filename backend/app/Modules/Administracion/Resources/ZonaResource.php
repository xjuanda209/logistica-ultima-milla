<?php

namespace App\Modules\Administracion\Resources;

use App\Modules\Administracion\Models\Usuario;
use App\Modules\Administracion\Models\Zona;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Zona
 */
class ZonaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'descripcion' => $this->descripcion,
            'repartidores' => $this->whenLoaded('repartidores', fn () => $this->repartidores->map(fn (Usuario $repartidor): array => [
                'id' => $repartidor->id,
                'nombre' => $repartidor->nombre,
            ])),
        ];
    }
}
