<?php

namespace App\Modules\Administracion\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Administracion\Models\Configuracion;
use App\Modules\Administracion\Requests\EditarConfiguracionRequest;
use App\Modules\Administracion\Resources\ConfiguracionResource;

/**
 * La configuración es una sola fila (ver Configuracion::actual()), por eso no lleva {id}.
 */
class ConfiguracionController extends Controller
{
    /**
     * GET /api/configuracion
     */
    public function show(): ConfiguracionResource
    {
        return new ConfiguracionResource(Configuracion::actual());
    }

    /**
     * PUT /api/configuracion
     */
    public function update(EditarConfiguracionRequest $request): ConfiguracionResource
    {
        $configuracion = Configuracion::actual();

        $configuracion->update($request->validated());

        return new ConfiguracionResource($configuracion);
    }
}
