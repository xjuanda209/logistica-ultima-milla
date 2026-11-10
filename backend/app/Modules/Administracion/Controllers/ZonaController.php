<?php

namespace App\Modules\Administracion\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Administracion\Models\Zona;
use App\Modules\Administracion\Requests\GuardarZonaRequest;
use App\Modules\Administracion\Resources\ZonaResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ZonaController extends Controller
{
    /**
     * GET /api/zonas — lista paginada. Con ?por_pagina=100 sirve para llenar un <select>.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filtros = $request->validate([
            'por_pagina' => ['nullable', 'integer', 'between:1,100'],
        ]);

        $zonas = Zona::query()
            ->orderBy('nombre')
            ->paginate((int) ($filtros['por_pagina'] ?? 20));

        return ZonaResource::collection($zonas);
    }

    /**
     * POST /api/zonas
     */
    public function store(GuardarZonaRequest $request): ZonaResource
    {
        return new ZonaResource(Zona::query()->create($request->validated()));
    }

    /**
     * PUT /api/zonas/{id}
     */
    public function update(GuardarZonaRequest $request, int $id): ZonaResource
    {
        $zona = Zona::query()->findOrFail($id);

        $zona->update($request->validated());

        return new ZonaResource($zona);
    }
}
