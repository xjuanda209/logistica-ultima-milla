<?php

namespace App\Modules\Despacho\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Pedidos\Models\Pedido;
use App\Shared\Enums\EstadoPedido;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PanelController extends Controller
{
    /**
     * GET /api/panel/resumen — cantidad de pedidos por estado (incluye los estados en cero).
     */
    public function resumen(Request $request): JsonResponse
    {
        $porEstado = $this->porEstado(Pedido::query()->visiblesPara($request->user()));

        return response()->json([
            'data' => [
                'total' => array_sum($porEstado),
                'por_estado' => $porEstado,
            ],
        ]);
    }

    /**
     * GET /api/panel/indicadores — indicadores del panel (INT-04), calculados con consultas
     * agregadas sobre los pedidos que cumplen los filtros (todos opcionales):
     *   ?desde= y ?hasta=   rango de la fecha de creación (instantes, como en GET /api/pedidos)
     *   ?zona_id=  ?comercio_id=  ?estado=
     * Devuelve:
     *   por_estado               pedidos en cada estado (incluye los que están en cero)
     *   porcentaje_exito         entregados ÷ (entregados + no entregados) × 100, o null sin base
     *   tiempo_promedio_minutos  de la creación a la entrega (hora del historial), o null sin entregas
     *   incidencias              por_tipo, por_zona y por_repartidor (el del pedido), de mayor a menor
     * La lista de pedidos del panel sale de GET /api/pedidos con los mismos filtros.
     */
    public function indicadores(Request $request): JsonResponse
    {
        $filtros = $request->validate([
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
            'zona_id' => ['nullable', 'integer'],
            'comercio_id' => ['nullable', 'integer'],
            'estado' => ['nullable', Rule::enum(EstadoPedido::class)],
        ], [
            'hasta.after_or_equal' => 'La fecha hasta no puede ser anterior a la fecha desde.',
        ]);

        // Las columnas van con el nombre de la tabla: las consultas de incidencias hacen join.
        $pedidos = fn (): Builder => Pedido::query()
            ->visiblesPara($request->user())
            ->when($filtros['desde'] ?? null, fn ($query, string $desde) => $query->where('pedidos.created_at', '>=', Carbon::parse($desde)->utc()))
            ->when($filtros['hasta'] ?? null, fn ($query, string $hasta) => $query->where('pedidos.created_at', '<=', Carbon::parse($hasta)->utc()))
            ->when($filtros['zona_id'] ?? null, fn ($query, $zonaId) => $query->where('pedidos.zona_id', $zonaId))
            ->when($filtros['comercio_id'] ?? null, fn ($query, $comercioId) => $query->where('pedidos.comercio_id', $comercioId))
            ->when($filtros['estado'] ?? null, fn ($query, string $estado) => $query->where('pedidos.estado', $estado));

        $porEstado = $this->porEstado($pedidos());
        $entregados = $porEstado[EstadoPedido::Entregado->value];
        $terminados = $entregados + $porEstado[EstadoPedido::NoEntregado->value];

        return response()->json([
            'data' => [
                'total' => array_sum($porEstado),
                'por_estado' => $porEstado,
                'porcentaje_exito' => $terminados === 0 ? null : round($entregados / $terminados * 100, 1),
                'tiempo_promedio_minutos' => $this->tiempoPromedio($pedidos()),
                'incidencias' => [
                    'por_tipo' => $this->incidenciasPor($pedidos(), 'tipos_incidencia', 'incidencias.tipo_incidencia_id'),
                    'por_zona' => $this->incidenciasPor($pedidos(), 'zonas', 'pedidos.zona_id'),
                    'por_repartidor' => $this->incidenciasPor($pedidos(), 'usuarios', 'pedidos.repartidor_id'),
                ],
            ],
        ]);
    }

    /**
     * @param  Builder<Pedido>  $pedidos
     * @return array<string, int>
     */
    private function porEstado(Builder $pedidos): array
    {
        $conteos = $pedidos
            ->toBase()
            ->selectRaw('pedidos.estado, count(*) as total')
            ->groupBy('pedidos.estado')
            ->pluck('total', 'estado');

        $porEstado = [];
        foreach (EstadoPedido::cases() as $estado) {
            $porEstado[$estado->value] = (int) ($conteos[$estado->value] ?? 0);
        }

        return $porEstado;
    }

    /**
     * Promedio, en minutos, entre la creación del pedido y el evento "entregado" de su historial.
     *
     * @param  Builder<Pedido>  $pedidos
     */
    private function tiempoPromedio(Builder $pedidos): ?int
    {
        // Segundos entre dos fechas: cada motor lo escribe distinto (Neon es PostgreSQL; las pruebas, SQLite).
        $segundos = DB::connection()->getDriverName() === 'pgsql'
            ? 'extract(epoch from (historial_pedido.created_at - pedidos.created_at))'
            : "(strftime('%s', historial_pedido.created_at) - strftime('%s', pedidos.created_at))";

        $promedio = $pedidos
            ->join('historial_pedido', 'historial_pedido.pedido_id', '=', 'pedidos.id')
            ->where('pedidos.estado', EstadoPedido::Entregado)
            ->where('historial_pedido.estado_nuevo', EstadoPedido::Entregado)
            // Los pedidos del seeder base tienen el historial antes de su created_at: no cuentan.
            ->whereColumn('historial_pedido.created_at', '>=', 'pedidos.created_at')
            ->toBase()
            ->selectRaw("avg({$segundos}) as promedio")
            ->value('promedio');

        return $promedio === null ? null : (int) round((float) $promedio / 60);
    }

    /**
     * Cuenta las incidencias de los pedidos agrupadas por el nombre de otra tabla (tipo, zona o
     * repartidor), de mayor a menor. Los pedidos sin repartidor no entran en "por repartidor".
     *
     * @param  Builder<Pedido>  $pedidos
     * @return list<array{etiqueta: string, valor: int}>
     */
    private function incidenciasPor(Builder $pedidos, string $tabla, string $llave): array
    {
        return $pedidos
            ->join('incidencias', 'incidencias.pedido_id', '=', 'pedidos.id')
            ->join($tabla, "{$tabla}.id", '=', $llave)
            ->toBase()
            ->selectRaw("{$tabla}.nombre as etiqueta, count(*) as valor")
            ->groupBy("{$tabla}.id", "{$tabla}.nombre")
            ->orderByDesc('valor')
            ->orderBy('etiqueta')
            ->get()
            ->map(fn (object $fila): array => ['etiqueta' => $fila->etiqueta, 'valor' => (int) $fila->valor])
            ->all();
    }
}
