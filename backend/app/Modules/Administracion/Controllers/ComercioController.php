<?php

namespace App\Modules\Administracion\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Administracion\Models\Comercio;
use App\Modules\Administracion\Resources\ComercioResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ComercioController extends Controller
{
    /**
     * GET /api/comercios — lista paginada. Con ?por_pagina=100 sirve para llenar un <select>.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filtros = $request->validate([
            'por_pagina' => ['nullable', 'integer', 'between:1,100'],
        ]);

        $comercios = Comercio::query()
            ->orderBy('nombre')
            ->paginate((int) ($filtros['por_pagina'] ?? 20));

        return ComercioResource::collection($comercios);
    }
}
