<?php

namespace App\Modules\Despacho\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Despacho\Requests\CambiarEstadoPedidoRequest;
use App\Modules\Despacho\Requests\GuardarEvidenciaRequest;
use App\Modules\Pedidos\Controllers\PedidoController;
use App\Modules\Pedidos\Models\Pedido;
use App\Modules\Pedidos\Resources\PedidoResource;
use App\Shared\Enums\Canal;
use App\Shared\Enums\EstadoPedido;
use App\Shared\Errores\ErrorApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Lo que hace el repartidor con sus pedidos desde la app móvil (INT-16 a INT-18): avanzar el
 * estado y registrar evidencias. Solo sobre pedidos propios: Pedido::scopeVisiblesPara() hace que
 * un pedido ajeno responda 404.
 */
class EntregaController extends Controller
{
    /**
     * PUT /api/pedidos/{id}/estado — recogido, en_ruta, entregado o no_entregado.
     * - entregado exige al menos una evidencia (FALTA_EVIDENCIA).
     * - no_entregado exige evidencia, una incidencia (FALTA_INCIDENCIA) y el motivo, que queda
     *   en el historial.
     */
    public function cambiarEstado(CambiarEstadoPedidoRequest $request, int $id): PedidoResource
    {
        $usuario = $request->user();
        $canal = Canal::deLaPeticion($request);
        $nuevo = EstadoPedido::from($request->validated('estado'));
        $motivo = $request->validated('motivo');

        DB::transaction(function () use ($usuario, $canal, $nuevo, $motivo, $id): void {
            $pedido = Pedido::query()->visiblesPara($usuario)->lockForUpdate()->findOrFail($id);
            $pedido->estado->exigirPasoA($nuevo);

            if ($nuevo === EstadoPedido::Entregado && ! $pedido->evidencias()->exists()) {
                throw new ErrorApi(
                    'FALTA_EVIDENCIA',
                    'Falta la evidencia: registra al menos una antes de marcarlo como entregado.',
                    409,
                );
            }

            if ($nuevo === EstadoPedido::NoEntregado) {
                if (! $pedido->evidencias()->exists()) {
                    throw new ErrorApi(
                        'FALTA_EVIDENCIA',
                        'Falta la evidencia: regístrala para marcar el pedido como no entregado.',
                        409,
                    );
                }

                if (! $pedido->incidencias()->exists()) {
                    throw new ErrorApi(
                        'FALTA_INCIDENCIA',
                        'Falta la incidencia: regístrala para marcar el pedido como no entregado.',
                        409,
                    );
                }
            }

            $pedido->cambiarEstado(
                $nuevo,
                $usuario,
                $canal,
                $nuevo === EstadoPedido::NoEntregado ? "Motivo: {$motivo}" : null,
            );
        });

        return $this->detalle($id);
    }

    /**
     * POST /api/pedidos/{id}/evidencias — guarda la imagen en el disco "public" y en la tabla solo
     * la ruta. Solo mientras el pedido está en recogido o en ruta.
     */
    public function guardarEvidencia(GuardarEvidenciaRequest $request, int $id): JsonResponse
    {
        $usuario = $request->user();
        $pedido = Pedido::query()->visiblesPara($usuario)->findOrFail($id);

        if (! $pedido->estado->admiteRegistrosDeEntrega()) {
            throw new ErrorApi(
                'PEDIDO_NO_ADMITE_EVIDENCIAS',
                'Solo puedes registrar evidencias mientras el pedido está «Recogido» o «En ruta».',
                409,
            );
        }

        $datos = $request->validated();

        $pedido->evidencias()->create([
            'usuario_id' => $usuario->id,
            'tipo' => $datos['tipo'],
            'ruta_archivo' => $request->file('archivo')->store("evidencias/{$pedido->id}", 'public'),
            'latitud' => $datos['latitud'] ?? null,
            'longitud' => $datos['longitud'] ?? null,
            'created_at' => now(),
        ]);

        return $this->detalle($id)->response()->setStatusCode(201);
    }

    private function detalle(int $id): PedidoResource
    {
        return new PedidoResource(Pedido::query()->with(PedidoController::RELACIONES_DETALLE)->findOrFail($id));
    }
}
