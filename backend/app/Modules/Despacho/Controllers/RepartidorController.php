<?php

namespace App\Modules\Despacho\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Administracion\Models\Configuracion;
use App\Modules\Administracion\Models\Usuario;
use App\Modules\Despacho\Requests\CambiarDisponibilidadRequest;
use App\Modules\Despacho\Resources\RepartidorResource;
use App\Shared\Enums\Rol;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RepartidorController extends Controller
{
    /**
     * GET /api/repartidores — repartidores (activos e inactivos) con su zona, disponibilidad y
     * carga, ordenados por nombre (INT-11).
     */
    public function index(): AnonymousResourceCollection
    {
        $maximo = Configuracion::actual()->max_pedidos_activos;

        $repartidores = Usuario::query()
            ->where('rol', Rol::Repartidor)
            ->with('zona:id,nombre')
            ->conPedidosActivos()
            ->orderBy('nombre')
            ->orderBy('id')
            ->paginate(20);

        $repartidores->getCollection()->each->setAttribute('max_pedidos_activos', $maximo);

        return RepartidorResource::collection($repartidores);
    }

    /**
     * PUT /api/repartidores/{id}/disponibilidad — administrador y operador cambian la de
     * cualquier repartidor; el repartidor, solo la suya (la de otro responde 404).
     * Pasar a "no_disponible" no le quita los pedidos que ya tiene: solo deja de recibir nuevos.
     */
    public function cambiarDisponibilidad(CambiarDisponibilidadRequest $request, int $id): RepartidorResource
    {
        $usuario = $request->user();

        $repartidor = Usuario::query()
            ->where('rol', Rol::Repartidor)
            ->when($usuario->rol === Rol::Repartidor, fn ($query) => $query->whereKey($usuario->id))
            ->with('zona:id,nombre')
            ->conPedidosActivos()
            ->findOrFail($id);

        $repartidor->update($request->validated());
        $repartidor->setAttribute('max_pedidos_activos', Configuracion::actual()->max_pedidos_activos);

        return new RepartidorResource($repartidor);
    }
}
