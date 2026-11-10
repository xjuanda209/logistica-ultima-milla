<?php

namespace App\Modules\Administracion\Resources;

use App\Modules\Administracion\Models\Configuracion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Configuracion
 */
class ConfiguracionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'max_pedidos_activos' => $this->max_pedidos_activos,
        ];
    }
}
