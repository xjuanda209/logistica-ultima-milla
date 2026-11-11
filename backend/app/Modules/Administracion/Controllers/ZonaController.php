<?php

namespace App\Modules\Administracion\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Administracion\Models\Zona;
use App\Modules\Administracion\Requests\GuardarZonaRequest;
use App\Modules\Administracion\Resources\ZonaResource;
use App\Shared\Enums\Rol;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ZonaController extends Controller
{
    /**
     * GET /api/zonas — lista paginada. Con ?por_pagina=100 sirve para llenar un <select>.
     * Con ?con_repartidores=1 cada zona trae los repartidores activos que la cubren (los chips de
     * Parametrización, INT-03). Solo para administrador y operador: a los demás roles se les ignora.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filtros = $request->validate([
            'por_pagina' => ['nullable', 'integer', 'between:1,100'],
            'con_repartidores' => ['nullable', 'boolean'],
        ]);

        $conRepartidores = $request->boolean('con_repartidores')
            && $request->user()->tieneRol(Rol::Administrador, Rol::Operador);

        $zonas = Zona::query()
            ->select(['id', 'nombre', 'descripcion'])
            ->when($conRepartidores, fn ($query) => $query->with([
                'repartidores' => fn ($repartidores) => $repartidores
                    ->select(['id', 'nombre', 'zona_id'])
                    ->where('rol', Rol::Repartidor)
                    ->where('activo', true)
                    ->orderBy('nombre'),
            ]))
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
