<?php

namespace Database\Seeders;

use App\Modules\Administracion\Models\Comercio;
use App\Modules\Administracion\Models\Usuario;
use App\Modules\Incidencias\Models\Incidencia;
use App\Modules\Incidencias\Models\TipoIncidencia;
use App\Modules\Pedidos\Models\Pedido;
use App\Shared\Enums\Canal;
use App\Shared\Enums\EstadoIncidencia;
use App\Shared\Enums\EstadoPedido;
use App\Shared\Enums\Prioridad;
use App\Shared\Enums\Rol;
use App\Shared\Enums\TipoEvento;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * 20 pedidos ficticios repartidos en los 7 estados, cada uno con sus líneas
 * y el historial de cómo llegó a ese estado. Los no entregados tienen una incidencia.
 */
class PedidosSeeder extends Seeder
{
    /** Cuántos pedidos se crean en cada estado (suman 20). */
    private const PEDIDOS_POR_ESTADO = [
        'creado' => 4,
        'asignado' => 3,
        'recogido' => 2,
        'en_ruta' => 3,
        'entregado' => 4,
        'no_entregado' => 2,
        'cancelado' => 2,
    ];

    private const PRODUCTOS = ['Caja de galletas', 'Arroz 1 kg', 'Jabón líquido', 'Vitamina C', 'Acetaminofén', 'Café 500 g'];

    public function run(): void
    {
        $operador = Usuario::query()->where('rol', Rol::Operador)->firstOrFail();
        $repartidor = Usuario::query()->where('rol', Rol::Repartidor)->firstOrFail();
        $comercios = Comercio::query()->with(['clientes', 'usuarios'])->orderBy('id')->get();
        $tipoAusente = TipoIncidencia::query()->where('nombre', 'Cliente ausente')->firstOrFail();

        $consecutivo = 0;

        foreach (self::PEDIDOS_POR_ESTADO as $valorEstado => $cantidad) {
            $estado = EstadoPedido::from($valorEstado);

            for ($i = 0; $i < $cantidad; $i++) {
                $consecutivo++;
                $comercio = $comercios[$consecutivo % $comercios->count()];
                $cliente = $comercio->clientes[$consecutivo % $comercio->clientes->count()];
                $creador = $comercio->usuarios->first() ?? $operador;
                $inicio = Carbon::today()->subDays(20 - $consecutivo)->setTime(8, 0);
                $tieneRepartidor = ! in_array($estado, [EstadoPedido::Creado, EstadoPedido::Cancelado], true);

                $pedido = Pedido::query()->firstOrCreate(
                    ['numero' => sprintf('PED-%04d', $consecutivo)],
                    [
                        'comercio_id' => $comercio->id,
                        'cliente_id' => $cliente->id,
                        'repartidor_id' => $tieneRepartidor ? $repartidor->id : null,
                        'creado_por' => $creador->id,
                        'estado' => $estado,
                        'prioridad' => Prioridad::cases()[$consecutivo % 3],
                        'direccion' => $cliente->direccion,
                        'barrio' => $cliente->barrio,
                        'indicaciones' => $cliente->indicaciones,
                        'zona_id' => $cliente->zona_id,
                        'franja_inicio' => $inicio->copy()->addDay(),
                        'franja_fin' => $inicio->copy()->addDay()->addHours(4),
                        'observaciones' => $consecutivo % 4 === 0 ? 'Entregar en portería' : null,
                    ],
                );

                if (! $pedido->wasRecentlyCreated) {
                    continue;
                }

                $pedido->lineas()->createMany([
                    ['producto' => self::PRODUCTOS[$consecutivo % 6], 'cantidad' => 1 + $consecutivo % 3],
                    ['producto' => self::PRODUCTOS[($consecutivo + 2) % 6], 'cantidad' => 1],
                ]);

                $this->crearHistorial($pedido, $estado, $creador, $operador, $repartidor, $inicio);

                if ($estado === EstadoPedido::NoEntregado) {
                    Incidencia::query()->create([
                        'pedido_id' => $pedido->id,
                        'tipo_incidencia_id' => $tipoAusente->id,
                        'usuario_id' => $repartidor->id,
                        'descripcion' => 'Se timbró varias veces y nadie atendió.',
                        'estado' => $i === 0 ? EstadoIncidencia::Abierta : EstadoIncidencia::EnRevision,
                        'prioridad' => Prioridad::Media,
                    ]);
                }
            }
        }
    }

    /**
     * Registra los eventos por los que pasó el pedido hasta su estado final.
     */
    private function crearHistorial(
        Pedido $pedido,
        EstadoPedido $estadoFinal,
        Usuario $creador,
        Usuario $operador,
        Usuario $repartidor,
        Carbon $inicio,
    ): void {
        $pedido->historial()->create([
            'usuario_id' => $creador->id,
            'tipo_evento' => TipoEvento::Creacion,
            'estado_anterior' => null,
            'estado_nuevo' => EstadoPedido::Creado,
            'detalle' => 'Pedido creado',
            'canal' => Canal::Web,
            'created_at' => $inicio,
        ]);

        $enRuta = [EstadoPedido::Asignado, EstadoPedido::Recogido, EstadoPedido::EnRuta];

        $pasos = match ($estadoFinal) {
            EstadoPedido::Creado => [],
            EstadoPedido::Asignado => [EstadoPedido::Asignado],
            EstadoPedido::Recogido => [EstadoPedido::Asignado, EstadoPedido::Recogido],
            EstadoPedido::EnRuta => $enRuta,
            EstadoPedido::Entregado => [...$enRuta, EstadoPedido::Entregado],
            EstadoPedido::NoEntregado => [...$enRuta, EstadoPedido::NoEntregado],
            EstadoPedido::Cancelado => [EstadoPedido::Cancelado],
        };

        $anterior = EstadoPedido::Creado;

        foreach ($pasos as $indice => $nuevo) {
            $loHaceElOperador = in_array($nuevo, [EstadoPedido::Asignado, EstadoPedido::Cancelado], true);

            $pedido->historial()->create([
                'usuario_id' => $loHaceElOperador ? $operador->id : $repartidor->id,
                'tipo_evento' => $nuevo === EstadoPedido::Asignado ? TipoEvento::Asignacion : TipoEvento::CambioEstado,
                'estado_anterior' => $anterior,
                'estado_nuevo' => $nuevo,
                'detalle' => match ($nuevo) {
                    EstadoPedido::Asignado => 'Asignado a '.$repartidor->nombre,
                    EstadoPedido::NoEntregado => 'Cliente ausente en la dirección',
                    EstadoPedido::Cancelado => 'Cancelado a solicitud del comercio',
                    default => null,
                },
                'canal' => $loHaceElOperador ? Canal::Web : Canal::Movil,
                'created_at' => $inicio->copy()->addMinutes(30 * ($indice + 1)),
            ]);

            $anterior = $nuevo;
        }
    }
}
