<?php

namespace App\Modules\Despacho\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Pedidos\Models\Pedido;
use App\Shared\Enums\EstadoPedido;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PanelController extends Controller
{
    /**
     * GET /api/panel/resumen — cantidad de pedidos por estado (incluye los estados en cero).
     */
    public function resumen(Request $request): JsonResponse
    {
        $conteos = Pedido::query()
            ->visiblesPara($request->user())
            ->toBase()
            ->selectRaw('estado, count(*) as total')
            ->groupBy('estado')
            ->pluck('total', 'estado');

        $porEstado = [];
        foreach (EstadoPedido::cases() as $estado) {
            $porEstado[$estado->value] = (int) ($conteos[$estado->value] ?? 0);
        }

        return response()->json([
            'data' => [
                'total' => array_sum($porEstado),
                'por_estado' => $porEstado,
            ],
        ]);
    }
}
