<?php

namespace App\Modules\Administracion\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Administracion\Requests\CambiarActivoRequest;
use App\Modules\Administracion\Requests\GuardarTipoIncidenciaRequest;
use App\Modules\Administracion\Resources\TipoIncidenciaResource;
use App\Modules\Incidencias\Models\TipoIncidencia;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Catálogo de tipos de incidencia. El modelo vive en el módulo Incidencias;
 * aquí solo está su administración.
 */
class TipoIncidenciaController extends Controller
{
    /**
     * GET /api/tipos-incidencia — lista paginada (activos e inactivos).
     * Con ?activos=1 devuelve solo los activos, y con ?por_pagina=100 sirve para un <select>:
     * es lo que debe pedir el formulario de reportar una incidencia.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filtros = $request->validate([
            'activos' => ['nullable', 'boolean'],
            'por_pagina' => ['nullable', 'integer', 'between:1,100'],
        ]);

        $tipos = TipoIncidencia::query()
            ->when($request->boolean('activos'), fn ($query) => $query->where('activo', true))
            ->orderBy('nombre')
            ->paginate((int) ($filtros['por_pagina'] ?? 20));

        return TipoIncidenciaResource::collection($tipos);
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
