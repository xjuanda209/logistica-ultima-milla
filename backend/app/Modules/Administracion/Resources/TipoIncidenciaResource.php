<?php

namespace App\Modules\Administracion\Resources;

use App\Modules\Incidencias\Models\TipoIncidencia;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TipoIncidencia
 */
class TipoIncidenciaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'activo' => $this->activo,
        ];
    }
}
