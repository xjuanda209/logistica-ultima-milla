<?php

namespace App\Modules\Administracion\Resources;

use App\Modules\Administracion\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Usuario
 */
class UsuarioResource extends JsonResource
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
            'rol' => $this->rol,
            'activo' => $this->activo,
            'comercio_id' => $this->comercio_id,
            'zona_id' => $this->zona_id,
            'disponibilidad' => $this->disponibilidad,
            'comercio' => $this->whenLoaded('comercio', fn (): ?array => $this->comercio === null ? null : [
                'id' => $this->comercio->id,
                'nombre' => $this->comercio->nombre,
            ]),
        ];
    }
}
