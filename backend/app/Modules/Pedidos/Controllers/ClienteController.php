<?php

namespace App\Modules\Pedidos\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Pedidos\Models\Cliente;
use App\Modules\Pedidos\Resources\ClienteResource;
use App\Shared\Enums\Rol;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ClienteController extends Controller
{
    /**
     * GET /api/clientes — el comercio ve solo sus clientes; administrador y operador ven todos.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $usuario = $request->user();

        $clientes = Cliente::query()
            ->when(
                $usuario->rol === Rol::Comercio,
                fn ($query) => $query->where('comercio_id', $usuario->comercio_id ?? 0),
            )
            ->with(['comercio', 'zona'])
            ->orderBy('nombre')
            ->paginate(20);

        return ClienteResource::collection($clientes);
    }
}
