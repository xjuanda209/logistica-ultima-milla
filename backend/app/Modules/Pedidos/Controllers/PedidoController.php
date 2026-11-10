<?php

namespace App\Modules\Pedidos\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Pedidos\Models\Pedido;
use App\Modules\Pedidos\Resources\PedidoResource;
use App\Shared\Enums\EstadoPedido;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class PedidoController extends Controller
{
    /**
     * GET /api/pedidos — lista paginada. Cada rol ve solo lo que le corresponde
     * (ver Pedido::scopeVisiblesPara). Filtros opcionales: ?estado=en_ruta y ?numero=PED-00
     * (pedidos cuyo número contiene ese texto, sin distinguir mayúsculas).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filtros = $request->validate([
            'estado' => ['nullable', Rule::enum(EstadoPedido::class)],
            'numero' => ['nullable', 'string', 'max:20'],
        ]);

        $pedidos = Pedido::query()
            ->visiblesPara($request->user())
            ->when($filtros['estado'] ?? null, fn ($query, string $estado) => $query->where('estado', $estado))
            ->when($filtros['numero'] ?? null, fn ($query, string $numero) => $query->whereLike('numero', "%{$numero}%"))
            ->with(['comercio', 'cliente', 'repartidor', 'zona'])
            ->latest('id')
            ->paginate(20);

        return PedidoResource::collection($pedidos);
    }

    /**
     * GET /api/pedidos/{id} — detalle con líneas e historial.
     * Si el pedido no es visible para el usuario responde 404.
     */
    public function show(Request $request, int $id): PedidoResource
    {
        $pedido = Pedido::query()
            ->visiblesPara($request->user())
            ->with(['comercio', 'cliente', 'repartidor', 'zona', 'lineas', 'historial.usuario'])
            ->findOrFail($id);

        return new PedidoResource($pedido);
    }
}
