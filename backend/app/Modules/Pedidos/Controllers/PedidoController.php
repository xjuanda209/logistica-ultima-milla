<?php

namespace App\Modules\Pedidos\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Administracion\Models\Comercio;
use App\Modules\Pedidos\Models\Cliente;
use App\Modules\Pedidos\Models\Pedido;
use App\Modules\Pedidos\Requests\GuardarPedidoRequest;
use App\Modules\Pedidos\Resources\PedidoResource;
use App\Shared\Enums\Canal;
use App\Shared\Enums\EstadoPedido;
use App\Shared\Enums\TipoEvento;
use App\Shared\Errores\ErrorApi;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PedidoController extends Controller
{
    /**
     * Relaciones que muestra PedidoResource, cargadas por adelantado (sin consultas N+1)
     * y solo con las columnas que usa.
     *
     * @var list<string>
     */
    private const RELACIONES_LISTA = [
        'comercio:id,nombre,direccion_recogida',
        'cliente:id,nombre,telefono',
        'repartidor:id,nombre',
        'zona:id,nombre',
    ];

    /**
     * Lo que muestra el detalle (INT-08), además de lo de la lista.
     *
     * @var list<string>
     */
    private const RELACIONES_DETALLE = [
        ...self::RELACIONES_LISTA,
        'lineas:id,pedido_id,producto,cantidad',
        'historial.usuario:id,nombre',
    ];

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
            ->with(self::RELACIONES_LISTA)
            ->latest('id')
            ->paginate(20);

        return PedidoResource::collection($pedidos);
    }

    /**
     * POST /api/pedidos — crea el pedido en estado "creado". El sistema pone el número (P-000001),
     * copia al pedido la dirección que el cliente tiene en ese momento y registra el evento
     * "creacion" en el historial.
     */
    public function store(GuardarPedidoRequest $request): PedidoResource
    {
        $datos = $request->validated();
        $comercio = Comercio::query()->findOrFail($datos['comercio_id']);

        if (! $comercio->activo) {
            throw new ErrorApi('COMERCIO_INACTIVO', 'No se pueden crear pedidos para un comercio desactivado.', 409);
        }

        $cliente = Cliente::query()->findOrFail($datos['cliente_id']);
        $usuario = $request->user();
        $canal = Canal::deLaPeticion($request);

        $crear = function () use ($datos, $cliente, $usuario, $canal): Pedido {
            $pedido = Pedido::query()->create([
                'numero' => Pedido::siguienteNumero(),
                'comercio_id' => $cliente->comercio_id,
                'cliente_id' => $cliente->id,
                'creado_por' => $usuario->id,
                'estado' => EstadoPedido::Creado,
                'prioridad' => $datos['prioridad'],
                'direccion' => $cliente->direccion,
                'barrio' => $cliente->barrio,
                'indicaciones' => $cliente->indicaciones,
                'zona_id' => $cliente->zona_id,
                'franja_inicio' => Carbon::parse($datos['franja_inicio'])->utc(),
                'franja_fin' => Carbon::parse($datos['franja_fin'])->utc(),
                'observaciones' => $datos['observaciones'] ?? null,
            ]);

            $pedido->lineas()->createMany(self::lineasLimpias($datos['lineas']));

            $pedido->historial()->create([
                'usuario_id' => $usuario->id,
                'tipo_evento' => TipoEvento::Creacion,
                'estado_anterior' => null,
                'estado_nuevo' => EstadoPedido::Creado,
                'detalle' => 'Pedido creado',
                'canal' => $canal,
                'created_at' => now(),
            ]);

            return $pedido;
        };

        return new PedidoResource($this->conNumeroUnico($crear)->load(self::RELACIONES_DETALLE));
    }

    /**
     * GET /api/pedidos/{id} — detalle con líneas e historial.
     * Si el pedido no es visible para el usuario responde 404.
     */
    public function show(Request $request, int $id): PedidoResource
    {
        $pedido = Pedido::query()
            ->visiblesPara($request->user())
            ->with(self::RELACIONES_DETALLE)
            ->findOrFail($id);

        return new PedidoResource($pedido);
    }

    /**
     * Ejecuta la creación dentro de una transacción. Si otra persona tomó el mismo número en ese
     * instante (la base lo rechaza por duplicado), lo intenta de nuevo con el siguiente.
     *
     * @param  callable(): Pedido  $crear
     */
    private function conNumeroUnico(callable $crear): Pedido
    {
        for ($intento = 1; ; $intento++) {
            try {
                return DB::transaction($crear);
            } catch (UniqueConstraintViolationException $e) {
                if ($intento >= 5) {
                    throw $e;
                }
            }
        }
    }

    /**
     * @param  array<int, array{producto: string, cantidad: int|string}>  $lineas
     * @return list<array{producto: string, cantidad: int}>
     */
    private static function lineasLimpias(array $lineas): array
    {
        return array_map(fn (array $linea): array => [
            'producto' => trim($linea['producto']),
            'cantidad' => (int) $linea['cantidad'],
        ], array_values($lineas));
    }
}
