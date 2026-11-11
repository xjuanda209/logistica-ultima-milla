<?php

namespace App\Modules\Incidencias\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Incidencias\Models\Incidencia;
use App\Modules\Incidencias\Resources\IncidenciaResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class IncidenciaController extends Controller
{
    /**
     * GET /api/incidencias — incidencias de los pedidos que el usuario puede ver.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $usuario = $request->user();

        $incidencias = Incidencia::query()
            ->whereHas('pedido', fn ($query) => $query->visiblesPara($usuario))
            ->with(['pedido:id,numero', 'tipo:id,nombre', 'usuario:id,nombre'])
            ->latest('id')
            ->paginate(20);

        return IncidenciaResource::collection($incidencias);
    }
}
