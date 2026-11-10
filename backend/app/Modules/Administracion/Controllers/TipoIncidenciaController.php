<?php

namespace App\Modules\Administracion\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Administracion\Requests\CambiarActivoRequest;
use App\Modules\Administracion\Requests\GuardarTipoIncidenciaRequest;
use App\Modules\Administracion\Resources\TipoIncidenciaResource;
use App\Modules\Incidencias\Models\TipoIncidencia;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Catálogo de tipos de incidencia. El modelo vive en el módulo Incidencias;
 * aquí solo está su administración.
 */
class TipoIncidenciaController extends Controller
{
    /**
     * GET /api/tipos-incidencia — lista paginada (activos e inactivos).
     */
    public function index(): AnonymousResourceCollection
    {
        return TipoIncidenciaResource::collection(
            TipoIncidencia::query()->orderBy('nombre')->paginate(20),
        );
    }

    /**
     * POST /api/tipos-incidencia — el tipo nuevo queda activo.
     */
    public function store(GuardarTipoIncidenciaRequest $request): TipoIncidenciaResource
    {
        $tipo = TipoIncidencia::query()->create([...$request->validated(), 'activo' => true]);

        return new TipoIncidenciaResource($tipo);
    }

    /**
     * PUT /api/tipos-incidencia/{id}
     */
    public function update(GuardarTipoIncidenciaRequest $request, int $id): TipoIncidenciaResource
    {
        $tipo = TipoIncidencia::query()->findOrFail($id);

        $tipo->update($request->validated());

        return new TipoIncidenciaResource($tipo);
    }

    /**
     * PUT /api/tipos-incidencia/{id}/estado — activa o desactiva el tipo.
     */
    public function cambiarEstado(CambiarActivoRequest $request, int $id): TipoIncidenciaResource
    {
        $tipo = TipoIncidencia::query()->findOrFail($id);

        $tipo->update(['activo' => $request->boolean('activo')]);

        return new TipoIncidenciaResource($tipo);
    }
}
