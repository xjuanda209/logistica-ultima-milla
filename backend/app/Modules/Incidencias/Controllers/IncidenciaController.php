<?php

namespace App\Modules\Incidencias\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Incidencias\Models\Incidencia;
use App\Modules\Incidencias\Requests\CambiarEstadoIncidenciaRequest;
use App\Modules\Incidencias\Requests\CambiarPrioridadIncidenciaRequest;
use App\Modules\Incidencias\Requests\GuardarNotaComercioRequest;
use App\Modules\Incidencias\Requests\RegistrarIncidenciaRequest;
use App\Modules\Incidencias\Resources\IncidenciaResource;
use App\Modules\Pedidos\Models\Pedido;
use App\Shared\Enums\EstadoIncidencia;
use App\Shared\Enums\Prioridad;
use App\Shared\Enums\Rol;
use App\Shared\Errores\ErrorApi;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class IncidenciaController extends Controller
{
    /**
     * Relaciones que muestra IncidenciaResource, solo con las columnas que usa.
     *
     * @var list<string>
     */
    private const RELACIONES = ['pedido:id,numero', 'tipo:id,nombre', 'usuario:id,nombre'];

    /**
     * GET /api/incidencias — incidencias de los pedidos que el usuario puede ver (INT-12), de la
     * más reciente a la más antigua. Filtros opcionales:
     *   ?estado=abierta   ?tipo_incidencia_id=2   ?prioridad=alta
     *   ?numero=PED-0001  incidencias de los pedidos cuyo número contiene ese texto
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filtros = $request->validate([
            'estado' => ['nullable', Rule::enum(EstadoIncidencia::class)],
            'tipo_incidencia_id' => ['nullable', 'integer'],
            'prioridad' => ['nullable', Rule::enum(Prioridad::class)],
            'numero' => ['nullable', 'string', 'max:20'],
        ]);

        $incidencias = Incidencia::query()
            ->visiblesPara($request->user())
            ->when($filtros['estado'] ?? null, fn ($query, string $estado) => $query->where('estado', $estado))
            ->when($filtros['tipo_incidencia_id'] ?? null, fn ($query, $tipoId) => $query->where('tipo_incidencia_id', $tipoId))
            ->when($filtros['prioridad'] ?? null, fn ($query, string $prioridad) => $query->where('prioridad', $prioridad))
            ->when($filtros['numero'] ?? null, fn ($query, string $numero) => $query->whereHas(
                'pedido',
                fn ($pedidos) => $pedidos->whereLike('numero', "%{$numero}%"),
            ))
            ->with(self::RELACIONES)
            ->latest('id')
            ->paginate(20);

        return IncidenciaResource::collection($incidencias);
    }

    /**
     * GET /api/incidencias/{id} — detalle (INT-13). La de un pedido ajeno responde 404.
     */
    public function show(Request $request, int $id): IncidenciaResource
    {
        $incidencia = Incidencia::query()
            ->visiblesPara($request->user())
            ->with(self::RELACIONES)
            ->findOrFail($id);

        return new IncidenciaResource($incidencia);
    }

    /**
     * POST /api/pedidos/{id}/incidencias — registra una incidencia (INT-19) en estado "abierta" y
     * sin prioridad. La foto es opcional: va al disco "public" y en la tabla solo queda la ruta.
     * - El repartidor (app móvil): solo en un pedido suyo (uno ajeno responde 404) que esté en
     *   recogido o en ruta.
     * - Administrador y operador (web, desde el detalle del pedido): en cualquier pedido y estado,
     *   por ejemplo cuando el cliente reclama después de la entrega.
     */
    public function store(RegistrarIncidenciaRequest $request, int $id): IncidenciaResource
    {
        $usuario = $request->user();
        $pedido = Pedido::query()->visiblesPara($usuario)->findOrFail($id);

        if ($usuario->rol === Rol::Repartidor && ! $pedido->estado->admiteRegistrosDeEntrega()) {
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

        return new IncidenciaResource($incidencia->load(self::RELACIONES));
    }

    /**
     * PUT /api/incidencias/{id}/prioridad — { "prioridad": "alta" }.
     */
    public function cambiarPrioridad(CambiarPrioridadIncidenciaRequest $request, int $id): IncidenciaResource
    {
        $incidencia = Incidencia::query()->with(self::RELACIONES)->findOrFail($id);
        $incidencia->update($request->validated());

        return new IncidenciaResource($incidencia);
    }

    /**
     * PUT /api/incidencias/{id}/estado — { "estado": "en_revision" } o
     * { "estado": "resuelta", "resolucion": "Se reprogramó la entrega." }.
     * Solo abierta → en revisión → resuelta (EstadoIncidencia::siguiente); para resolver, la
     * resolución es obligatoria.
     */
    public function cambiarEstado(CambiarEstadoIncidenciaRequest $request, int $id): IncidenciaResource
    {
        $nuevo = EstadoIncidencia::from($request->validated('estado'));
        $resolucion = $request->validated('resolucion');

        DB::transaction(function () use ($nuevo, $resolucion, $id): void {
            $incidencia = Incidencia::query()->lockForUpdate()->findOrFail($id);
            $incidencia->estado->exigirPasoA($nuevo);

            $incidencia->estado = $nuevo;
            if ($nuevo === EstadoIncidencia::Resuelta) {
                $incidencia->resolucion = $resolucion;
            }
            $incidencia->save();
        });

        return new IncidenciaResource(Incidencia::query()->with(self::RELACIONES)->findOrFail($id));
    }

    /**
     * PUT /api/incidencias/{id}/nota — { "nota_comercio": "Intentaremos de nuevo en la tarde." }.
     * Hay una sola nota para el comercio: guardar la reemplaza. No puede quedar vacía.
     */
    public function guardarNota(GuardarNotaComercioRequest $request, int $id): IncidenciaResource
    {
        $incidencia = Incidencia::query()->with(self::RELACIONES)->findOrFail($id);
        $incidencia->update($request->validated());

        return new IncidenciaResource($incidencia);
    }
}
