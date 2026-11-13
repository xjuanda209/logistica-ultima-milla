<?php

namespace App\Modules\Incidencias\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Incidencias\Models\Incidencia;
use App\Modules\Incidencias\Requests\RegistrarIncidenciaRequest;
use App\Modules\Incidencias\Resources\IncidenciaResource;
use App\Modules\Pedidos\Models\Pedido;
use App\Shared\Enums\EstadoIncidencia;
use App\Shared\Errores\ErrorApi;
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

    /**
     * POST /api/pedidos/{id}/incidencias — el repartidor reporta una incidencia de un pedido suyo
     * (uno ajeno responde 404) que está en recogido o en ruta. Queda en estado "abierta" y sin
     * prioridad (la pone el operador). La foto es opcional: va al disco "public" y en la tabla
     * solo queda la ruta.
     */
    public function store(RegistrarIncidenciaRequest $request, int $id): IncidenciaResource
    {
        $usuario = $request->user();
        $pedido = Pedido::query()->visiblesPara($usuario)->findOrFail($id);

        if (! $pedido->estado->admiteRegistrosDeEntrega()) {
            throw new ErrorApi(
                'PEDIDO_NO_ADMITE_INCIDENCIAS',
                'No puedes registrar incidencias en este pedido: no está «Recogido» ni «En ruta».',
                409,
            );
        }

        $datos = $request->validated();

        $incidencia = $pedido->incidencias()->create([
            'tipo_incidencia_id' => $datos['tipo_incidencia_id'],
            'usuario_id' => $usuario->id,
            'descripcion' => $datos['descripcion'],
            'ruta_foto' => $request->file('foto')?->store("incidencias/{$pedido->id}", 'public'),
            'estado' => EstadoIncidencia::Abierta,
        ]);

        return new IncidenciaResource($incidencia->load(['pedido:id,numero', 'tipo:id,nombre', 'usuario:id,nombre']));
    }
}
