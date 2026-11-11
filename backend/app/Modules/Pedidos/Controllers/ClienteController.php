<?php

namespace App\Modules\Pedidos\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Pedidos\Models\Cliente;
use App\Modules\Pedidos\Requests\GuardarClienteRequest;
use App\Modules\Pedidos\Resources\ClienteResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ClienteController extends Controller
{
    /** @var list<string> */
    private const RELACIONES = ['comercio:id,nombre', 'zona:id,nombre'];

    /**
     * GET /api/clientes — el comercio ve solo sus clientes; administrador y operador ven todos
     * y pueden filtrar con ?comercio_id=. Con ?buscar= filtra por nombre, cédula o teléfono
     * (sin distinguir mayúsculas). Con ?por_pagina=100 sirve para llenar un <select>.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filtros = $request->validate([
            'buscar' => ['nullable', 'string', 'max:60'],
            'comercio_id' => ['nullable', 'integer'],
            'por_pagina' => ['nullable', 'integer', 'between:1,100'],
        ]);

        $clientes = Cliente::query()
            ->visiblesPara($request->user())
            ->when($filtros['comercio_id'] ?? null, fn ($query, $comercioId) => $query->where('comercio_id', $comercioId))
            ->when($filtros['buscar'] ?? null, fn ($query, string $texto) => $query->where(
                fn ($coincide) => $coincide
                    ->whereLike('nombre', "%{$texto}%")
                    ->orWhereLike('cedula', "%{$texto}%")
                    ->orWhereLike('telefono', "%{$texto}%"),
            ))
            ->with(self::RELACIONES)
            ->orderBy('nombre')
            ->orderBy('id')
            ->paginate((int) ($filtros['por_pagina'] ?? 20));

        return ClienteResource::collection($clientes);
    }

    /**
     * POST /api/clientes
     */
    public function store(GuardarClienteRequest $request): ClienteResource
    {
        $cliente = Cliente::query()->create($request->validated());

        return new ClienteResource($cliente->load(self::RELACIONES));
    }

    /**
     * PUT /api/clientes/{id} — el comercio del cliente no cambia. Los pedidos ya creados
     * conservan la dirección que se copió al crearlos.
     */
    public function update(GuardarClienteRequest $request, int $id): ClienteResource
    {
        $cliente = $request->cliente();

        $cliente->update($request->validated());

        return new ClienteResource($cliente->load(self::RELACIONES));
    }
}
