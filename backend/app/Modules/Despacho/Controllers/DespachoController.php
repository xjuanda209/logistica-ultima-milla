<?php

namespace App\Modules\Despacho\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Administracion\Models\Configuracion;
use App\Modules\Administracion\Models\Usuario;
use App\Modules\Despacho\Requests\AsignarRepartidorRequest;
use App\Modules\Despacho\Requests\CancelarPedidoRequest;
use App\Modules\Despacho\Resources\RepartidorResource;
use App\Modules\Pedidos\Controllers\PedidoController;
use App\Modules\Pedidos\Models\Pedido;
use App\Modules\Pedidos\Resources\PedidoResource;
use App\Shared\Enums\Canal;
use App\Shared\Enums\EstadoPedido;
use App\Shared\Enums\Rol;
use App\Shared\Enums\TipoEvento;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

/**
 * Asignar, reasignar y cancelar pedidos (INT-09 e INT-10). Las reglas no están aquí:
 * las transiciones en EstadoPedido y quién puede recibir un pedido en Usuario::motivoParaNoRecibir().
 */
class DespachoController extends Controller
{
    /**
     * GET /api/pedidos/{id}/repartidores — todos los repartidores activos, para la ventana de
     * asignación: primero los que pueden recibir el pedido, de menor a mayor carga; después los
     * que no, con el motivo. No se pagina: la ventana los muestra todos.
     */
    public function candidatos(Request $request, int $id): AnonymousResourceCollection
    {
        $pedido = Pedido::query()->visiblesPara($request->user())->findOrFail($id);
        $maximo = Configuracion::actual()->max_pedidos_activos;

        $repartidores = Usuario::query()
            ->where('rol', Rol::Repartidor)
            ->where('activo', true)
            ->with('zona:id,nombre')
            ->conPedidosActivos()
            ->orderBy('nombre')
            ->get()
            ->each(function (Usuario $repartidor) use ($pedido, $maximo): void {
                $repartidor->setAttribute('max_pedidos_activos', $maximo);
                $repartidor->setAttribute(
                    'motivo',
                    $repartidor->motivoParaNoRecibir($pedido, (int) $repartidor->pedidos_activos, $maximo)?->etiqueta(),
                );
            })
            ->sortBy(fn (Usuario $repartidor): array => [
                $repartidor->motivo === null ? 0 : 1,
                (int) $repartidor->pedidos_activos,
                $repartidor->nombre,
            ])
            ->values();

        return RepartidorResource::collection($repartidores);
    }

    /**
     * PUT /api/pedidos/{id}/repartidor — asigna el pedido (creado → asignado) o lo reasigna a otro
     * repartidor (asignado → asignado). Si el repartidor no puede recibirlo responde 409 con el
     * motivo: MISMO_REPARTIDOR, REPARTIDOR_DESACTIVADO, REPARTIDOR_NO_DISPONIBLE,
     * REPARTIDOR_FUERA_DE_ZONA o REPARTIDOR_AL_MAXIMO.
     */
    public function asignar(AsignarRepartidorRequest $request, int $id): PedidoResource
    {
        $usuario = $request->user();
        $canal = Canal::deLaPeticion($request);
        $repartidorId = (int) $request->validated('repartidor_id');

        DB::transaction(function () use ($usuario, $canal, $repartidorId, $id): void {
            $pedido = Pedido::query()->visiblesPara($usuario)->lockForUpdate()->findOrFail($id);
            $pedido->estado->exigirPasoA(EstadoPedido::Asignado);

            // Se bloquea también al repartidor: dos asignaciones simultáneas no lo pasan del máximo.
            $repartidor = Usuario::query()
                ->where('rol', Rol::Repartidor)
                ->lockForUpdate()
                ->findOrFail($repartidorId);

            $activos = $repartidor->pedidosAsignados()->whereIn('estado', EstadoPedido::activos())->count();
            $motivo = $repartidor->motivoParaNoRecibir($pedido, $activos, Configuracion::actual()->max_pedidos_activos);

            if ($motivo !== null) {
                throw $motivo->error();
            }

            $anterior = $pedido->repartidor_id === null
                ? null
                : Usuario::query()->whereKey($pedido->repartidor_id)->value('nombre');

            $pedido->repartidor_id = $repartidor->id;
            $pedido->cambiarEstado(
                EstadoPedido::Asignado,
                $usuario,
                $canal,
                "Repartidor: {$repartidor->nombre}".($anterior === null ? '' : " (antes: {$anterior})"),
                TipoEvento::Asignacion,
            );
        });

        return $this->detalle($id);
    }

    /**
     * PUT /api/pedidos/{id}/cancelacion — cancela un pedido en creado o asignado. El motivo es
     * obligatorio y queda en el historial. El pedido conserva el repartidor que tenía, pero deja
     * de contarle como pedido activo.
     */
    public function cancelar(CancelarPedidoRequest $request, int $id): PedidoResource
    {
        $usuario = $request->user();
        $canal = Canal::deLaPeticion($request);
        $motivo = $request->validated('motivo');

        DB::transaction(function () use ($usuario, $canal, $motivo, $id): void {
            Pedido::query()
                ->visiblesPara($usuario)
                ->lockForUpdate()
                ->findOrFail($id)
                ->cambiarEstado(EstadoPedido::Cancelado, $usuario, $canal, "Motivo: {$motivo}");
        });

        return $this->detalle($id);
    }

    private function detalle(int $id): PedidoResource
    {
        return new PedidoResource(Pedido::query()->with(PedidoController::RELACIONES_DETALLE)->findOrFail($id));
    }
}
