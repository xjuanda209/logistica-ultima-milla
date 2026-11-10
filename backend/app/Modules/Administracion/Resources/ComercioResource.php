<?php

namespace App\Modules\Administracion\Resources;

use App\Modules\Administracion\Models\Comercio;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Comercio
 */
class ComercioResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'direccion_recogida' => $this->direccion_recogida,
            'activo' => $this->activo,
        ];
    }
}
