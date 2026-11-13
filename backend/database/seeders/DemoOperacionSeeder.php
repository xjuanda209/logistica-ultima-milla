<?php

namespace Database\Seeders;

use App\Modules\Administracion\Models\Comercio;
use App\Modules\Administracion\Models\Usuario;
use App\Modules\Administracion\Models\Zona;
use App\Modules\Incidencias\Models\Incidencia;
use App\Modules\Incidencias\Models\TipoIncidencia;
use App\Modules\Pedidos\Models\Cliente;
use App\Modules\Pedidos\Models\HistorialPedido;
use App\Modules\Pedidos\Models\Pedido;
use App\Shared\Enums\Canal;
use App\Shared\Enums\Disponibilidad;
use App\Shared\Enums\EstadoIncidencia;
use App\Shared\Enums\EstadoPedido;
use App\Shared\Enums\Prioridad;
use App\Shared\Enums\Rol;
use App\Shared\Enums\TipoEvento;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Datos de demostración de la operación (panel INT-04 y app móvil):
 *   - 4 repartidores más (repartidor2@ a repartidor5@logistica.test, contraseña "password"), en
 *     zonas distintas y con distinta disponibilidad;
 *   - 15 pedidos en estados variados, creados en los últimos días, con su historial y horas
 *     coherentes. 3 quedan en "asignado" a repartidor@logistica.test para probar el móvil;
 *   - una incidencia en cada pedido no entregado y otra, abierta, en un pedido en ruta.
 * No crea evidencias: una evidencia necesita un archivo real, y la base es compartida.
 *
 * Es idempotente y solo agrega: no borra ni modifica nada. Los usuarios se crean con firstOrCreate
 * (por correo) y los pedidos solo si todavía no existen (se reconocen por el texto del evento
 * "creacion" del historial, que nunca se edita). No forma parte de DatabaseSeeder:
 *
 *   php artisan db:seed --class=DemoOperacionSeeder
 *
 * Necesita los datos de DatabaseSeeder (zonas, comercios, clientes, tipos de incidencia y usuarios).
 */
class DemoOperacionSeeder extends Seeder
{
    /** Detalle del evento "creacion" de estos pedidos: sirve para no sembrarlos dos veces. */
    public const MARCA = 'Pedido creado (datos de demostración)';

    private const PRINCIPAL = 'repartidor@logistica.test';

    /** correo => [nombre, zona, disponibilidad] */
    private const REPARTIDORES = [
        'repartidor2@logistica.test' => ['Rosa Repartidora', 'Norte', Disponibilidad::Disponible],
        'repartidor3@logistica.test' => ['Ramiro Rutas', 'Sur', Disponibilidad::NoDisponible],
        'repartidor4@logistica.test' => ['Regina Reparto', 'Occidente', Disponibilidad::Disponible],
        'repartidor5@logistica.test' => ['Raúl Recorridos', 'Centro', Disponibilidad::NoDisponible],
    ];

    /**
     * [estado final, correo del repartidor, hace cuántas horas se creó, minutos (desde la creación)
     * de cada paso del historial, tipo de la incidencia].
     */
    private const PEDIDOS = [
        ['creado', null, 3, [], null],
        ['creado', null, 1, [], null],
        ['asignado', self::PRINCIPAL, 5, [20], null],
        ['asignado', self::PRINCIPAL, 4, [15], null],
        ['asignado', self::PRINCIPAL, 2, [10], null],
        ['asignado', 'repartidor2@logistica.test', 3, [25], null],
        ['recogido', 'repartidor2@logistica.test', 6, [15, 70], null],
        ['en_ruta', 'repartidor4@logistica.test', 5, [10, 60, 95], null],
        ['en_ruta', 'repartidor3@logistica.test', 7, [20, 80, 120], 'Dirección incorrecta'],
        ['entregado', 'repartidor2@logistica.test', 30, [15, 60, 90, 135], null],
        ['entregado', 'repartidor4@logistica.test', 54, [10, 45, 70, 110], null],
        ['entregado', self::PRINCIPAL, 78, [30, 90, 120, 175], null],
        ['no_entregado', 'repartidor2@logistica.test', 50, [20, 70, 100, 150], 'Cliente ausente'],
        ['no_entregado', 'repartidor4@logistica.test', 98, [15, 65, 95, 160], 'Cliente rechaza el pedido'],
        ['cancelado', null, 26, [45], null],
    ];

    private const PRODUCTOS = [
        'Pan de bono x12', 'Torta de chocolate', 'Caja de galletas', 'Café 500 g',
        'Vitamina C', 'Acetaminofén', 'Jabón líquido', 'Arroz 1 kg',
    ];

    public function run(): void
    {
        $operador = Usuario::query()->where('rol', Rol::Operador)->orderBy('id')->first();
        $principal = Usuario::query()->where('correo', self::PRINCIPAL)->first();
        $zonas = Zona::query()->pluck('id', 'nombre');

        if ($operador === null || $principal === null || collect(self::REPARTIDORES)->contains(fn (array $datos): bool => ! $zonas->has($datos[1]))) {
            $this->command?->warn('Faltan zonas o usuarios base: ejecuta primero los seeders base.');

            return;
        }

        // Todo o nada: si algo falla a mitad de camino no quedan pedidos a medias.
        DB::transaction(function () use ($operador, $principal, $zonas): void {
            $repartidores = [self::PRINCIPAL => $principal];

            foreach (self::REPARTIDORES as $correo => [$nombre, $zona, $disponibilidad]) {
                $repartidores[$correo] = Usuario::query()->firstOrCreate(['correo' => $correo], [
                    'nombre' => $nombre,
                    'password' => 'password',
                    'rol' => Rol::Repartidor,
                    'activo' => true,
                    'zona_id' => $zonas[$zona],
                    'disponibilidad' => $disponibilidad,
                ]);
            }

            if (HistorialPedido::query()->where('detalle', self::MARCA)->exists()) {
                $this->command?->info('Los pedidos de demostración de la operación ya existen: no se crean más.');

                return;
            }

            $clientes = Cliente::query()->orderBy('id')->get();
            $comercios = Comercio::query()->with('usuarios')->get()->keyBy('id');

            foreach (self::PEDIDOS as $indice => [$estado, $correo, $horas, $minutos, $tipoIncidencia]) {
                $repartidor = $correo === null ? null : $repartidores[$correo];
                $cliente = $this->clientePara($clientes, $repartidor, $indice);

                if ($cliente === null) {
                    $this->command?->warn("No hay clientes en la zona de {$correo}: se omite un pedido.");

                    continue;
                }

                $creador = $comercios[$cliente->comercio_id]->usuarios->first() ?? $operador;
                $pedido = $this->crearPedido($indice, EstadoPedido::from($estado), $cliente, $creador, $operador, $repartidor, $horas, $minutos);

                if ($tipoIncidencia !== null && $repartidor !== null) {
                    $this->crearIncidencia($pedido, $repartidor, $tipoIncidencia, $indice);
                }
            }
        });
    }

    /**
     * Un cliente de la zona del repartidor (el pedido copia la zona del cliente y solo se asigna
     * a repartidores de esa zona). Sin repartidor, cualquiera, por turnos.
     *
     * @param  Collection<int, Cliente>  $clientes
     */
    private function clientePara(Collection $clientes, ?Usuario $repartidor, int $indice): ?Cliente
    {
        $candidatos = $repartidor === null ? $clientes : $clientes->where('zona_id', $repartidor->zona_id)->values();

        return $candidatos->isEmpty() ? null : $candidatos[$indice % $candidatos->count()];
    }

    /**
     * Crea el pedido ya en su estado final, con las líneas y un evento del historial por cada
     * paso (asignado, recogido, en ruta...), a los minutos indicados después de la creación.
     *
     * @param  list<int>  $minutos
     */
    private function crearPedido(
        int $indice,
        EstadoPedido $estado,
        Cliente $cliente,
        Usuario $creador,
        Usuario $operador,
        ?Usuario $repartidor,
        int $horas,
        array $minutos,
    ): Pedido {
        $creado = Carbon::now()->subHours($horas)->startOfMinute();
        $ultimoCambio = $creado->copy()->addMinutes($minutos === [] ? 0 : max($minutos));
        // Los pedidos en curso se entregan hoy; los terminados tenían la franja el día en que se crearon.
        $inicio = in_array($estado, [EstadoPedido::Creado, ...EstadoPedido::activos()], true)
            ? Carbon::now()->addHour()->startOfHour()
            : $creado->copy()->addHour();

        $pedido = Pedido::query()->create([
            'numero' => Pedido::siguienteNumero(),
            'comercio_id' => $cliente->comercio_id,
            'cliente_id' => $cliente->id,
            'repartidor_id' => $repartidor?->id,
            'creado_por' => $creador->id,
            'estado' => $estado,
            'prioridad' => Prioridad::cases()[$indice % 3],
            'direccion' => $cliente->direccion,
            'barrio' => $cliente->barrio,
            'indicaciones' => $cliente->indicaciones,
            'zona_id' => $cliente->zona_id,
            'franja_inicio' => $inicio,
            'franja_fin' => $inicio->copy()->addHours(3),
            'observaciones' => $indice % 4 === 0 ? 'Entregar en portería.' : null,
        ]);

        $pedido->forceFill(['created_at' => $creado, 'updated_at' => $ultimoCambio])->save();

        $pedido->lineas()->createMany([
            ['producto' => self::PRODUCTOS[$indice % 8], 'cantidad' => 1 + $indice % 3],
            ['producto' => self::PRODUCTOS[($indice + 3) % 8], 'cantidad' => 1],
        ]);

        $pedido->historial()->create([
            'usuario_id' => $creador->id,
            'tipo_evento' => TipoEvento::Creacion,
            'estado_anterior' => null,
            'estado_nuevo' => EstadoPedido::Creado,
            'detalle' => self::MARCA,
            'canal' => Canal::Web,
            'created_at' => $creado,
        ]);

        $anterior = EstadoPedido::Creado;

        foreach (self::pasosHasta($estado) as $numero => $nuevo) {
            // Asignar y cancelar los hace el operador en la web; lo demás, el repartidor en el móvil.
            $loHaceElOperador = in_array($nuevo, [EstadoPedido::Asignado, EstadoPedido::Cancelado], true);

            $pedido->historial()->create([
                'usuario_id' => $loHaceElOperador || $repartidor === null ? $operador->id : $repartidor->id,
                'tipo_evento' => $nuevo === EstadoPedido::Asignado ? TipoEvento::Asignacion : TipoEvento::CambioEstado,
                'estado_anterior' => $anterior,
                'estado_nuevo' => $nuevo,
                'detalle' => match ($nuevo) {
                    EstadoPedido::Asignado => 'Repartidor: '.$repartidor?->nombre,
                    EstadoPedido::NoEntregado => 'Motivo: no fue posible entregar el pedido en la dirección.',
                    EstadoPedido::Cancelado => 'Motivo: el comercio pidió cancelar el pedido.',
                    default => null,
                },
                'canal' => $loHaceElOperador ? Canal::Web : Canal::Movil,
                'created_at' => $creado->copy()->addMinutes($minutos[$numero]),
            ]);

            $anterior = $nuevo;
        }

        return $pedido;
    }

    /**
     * Estados por los que pasa un pedido, después de "creado", hasta llegar al estado dado.
     *
     * @return list<EstadoPedido>
     */
    private static function pasosHasta(EstadoPedido $estado): array
    {
        $enRuta = [EstadoPedido::Asignado, EstadoPedido::Recogido, EstadoPedido::EnRuta];

        return match ($estado) {
            EstadoPedido::Creado => [],
            EstadoPedido::Asignado => [EstadoPedido::Asignado],
            EstadoPedido::Recogido => [EstadoPedido::Asignado, EstadoPedido::Recogido],
            EstadoPedido::EnRuta => $enRuta,
            EstadoPedido::Entregado => [...$enRuta, EstadoPedido::Entregado],
            EstadoPedido::NoEntregado => [...$enRuta, EstadoPedido::NoEntregado],
            EstadoPedido::Cancelado => [EstadoPedido::Cancelado],
        };
    }

    /**
     * Incidencia que reportó el repartidor estando en ruta (10 minutos después de salir).
     * Quedan abiertas, salvo una que ya está en revisión y con prioridad.
     */
    private function crearIncidencia(Pedido $pedido, Usuario $repartidor, string $nombreTipo, int $indice): void
    {
        $tipo = TipoIncidencia::query()->where('nombre', $nombreTipo)->first()
            ?? TipoIncidencia::query()->orderBy('id')->firstOrFail();
        $enRevision = $indice % 2 === 1;
        $reportada = $pedido->historial()->where('estado_nuevo', EstadoPedido::EnRuta)->firstOrFail()
            ->created_at->copy()->addMinutes(10);

        $incidencia = Incidencia::query()->create([
            'pedido_id' => $pedido->id,
            'tipo_incidencia_id' => $tipo->id,
            'usuario_id' => $repartidor->id,
            'descripcion' => match ($nombreTipo) {
                'Cliente ausente' => 'Se timbró varias veces y nadie atendió.',
                'Dirección incorrecta' => 'La dirección no existe en el barrio indicado.',
                default => 'El cliente no quiso recibir el pedido.',
            },
            'estado' => $enRevision ? EstadoIncidencia::EnRevision : EstadoIncidencia::Abierta,
            'prioridad' => $enRevision ? Prioridad::Alta : null,
        ]);

        $incidencia->forceFill(['created_at' => $reportada, 'updated_at' => $reportada])->save();
    }
}
