<?php

namespace Database\Seeders;

use App\Modules\Administracion\Models\Comercio;
use App\Modules\Administracion\Models\Usuario;
use App\Modules\Administracion\Models\Zona;
use App\Modules\Pedidos\Models\Cliente;
use App\Modules\Pedidos\Models\Pedido;
use App\Shared\Enums\Canal;
use App\Shared\Enums\EstadoPedido;
use App\Shared\Enums\Prioridad;
use App\Shared\Enums\Rol;
use App\Shared\Enums\TipoEvento;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Datos de demostración del módulo de Clientes y Pedidos: 8 clientes repartidos entre los
 * comercios y 12 pedidos en estado "creado" (P-000001 a P-000012), con productos e historial.
 *
 * Es idempotente y solo agrega: usa firstOrCreate, no borra ni modifica nada. Si un cliente
 * (comercio + cédula) o un número de pedido ya existe, lo deja como está.
 * No forma parte de DatabaseSeeder; se ejecuta aparte:
 *
 *   php artisan db:seed --class=DemoPedidosSeeder
 *
 * Necesita los catálogos y usuarios de DatabaseSeeder (zonas, comercios y un operador).
 */
class DemoPedidosSeeder extends Seeder
{
    /** [nombre, cédula, teléfono, dirección, barrio, indicaciones] */
    private const CLIENTES = [
        ['Natalia Restrepo', '1088300101', '3105550101', 'Cra 8 # 20-15, apto 301', 'Centro', 'Portería, torre B'],
        ['Santiago Marín', '1088300102', '3105550102', 'Calle 14 # 5-40', 'Cuba', null],
        ['Luisa Fernanda Cardona', '1088300103', '3105550103', 'Av. 30 de Agosto # 38-22', 'Maraya', 'Local 2, timbre dañado'],
        ['Esteban Giraldo', '1088300104', '3105550104', 'Mz 12 casa 7', 'El Poblado', null],
        ['Daniela Arango', '1088300105', '3105550105', 'Calle 21 # 9-51, oficina 402', 'Centro', 'Dejar en recepción'],
        ['Jorge Iván Ríos', '1088300106', '3105550106', 'Cra 15 # 71-30', 'Pinares', null],
        ['Carolina Zapata', '1088300107', '3105550107', 'Calle 50 # 12-18', 'Los Álamos', 'Llamar antes de llegar'],
        ['Felipe Montoya', '1088300108', '3105550108', 'Cra 4 # 28-64', 'San Nicolás', null],
    ];

    private const PRODUCTOS = [
        'Pan de bono x12', 'Torta de chocolate', 'Caja de galletas', 'Café 500 g',
        'Vitamina C', 'Acetaminofén', 'Jabón líquido', 'Arroz 1 kg',
    ];

    private const OBSERVACIONES = [null, 'Entregar en portería.', null, 'El cliente paga contra entrega.'];

    private const CANTIDAD_DE_PEDIDOS = 12;

    public function run(): void
    {
        $comercios = Comercio::query()->with('usuarios')->orderBy('id')->get();
        $zonas = Zona::query()->orderBy('id')->get();
        $operador = Usuario::query()->where('rol', Rol::Operador)->orderBy('id')->firstOrFail();

        if ($comercios->isEmpty() || $zonas->isEmpty()) {
            $this->command?->warn('Faltan comercios o zonas: ejecuta primero los seeders base.');

            return;
        }

        $clientes = $this->crearClientes($comercios, $zonas);

        for ($i = 1; $i <= self::CANTIDAD_DE_PEDIDOS; $i++) {
            $cliente = $clientes[($i - 1) % count($clientes)];
            $creador = $comercios->firstWhere('id', $cliente->comercio_id)?->usuarios->first() ?? $operador;
            // Creados en los últimos días (el 12 es el más reciente) y con la entrega para mañana o pasado.
            $creado = Carbon::now()->subHours(6 * (self::CANTIDAD_DE_PEDIDOS - $i) + 1)->startOfMinute();
            $inicio = Carbon::tomorrow()->addDays($i % 2)->setTime(8 + $i % 9, 0);

            $pedido = Pedido::query()->firstOrCreate(
                ['numero' => sprintf('P-%06d', $i)],
                [
                    'comercio_id' => $cliente->comercio_id,
                    'cliente_id' => $cliente->id,
                    'repartidor_id' => null,
                    'creado_por' => $creador->id,
                    'estado' => EstadoPedido::Creado,
                    'prioridad' => Prioridad::cases()[$i % 3],
                    'direccion' => $cliente->direccion,
                    'barrio' => $cliente->barrio,
                    'indicaciones' => $cliente->indicaciones,
                    'zona_id' => $cliente->zona_id,
                    'franja_inicio' => $inicio,
                    'franja_fin' => $inicio->copy()->addHours(2),
                    'observaciones' => self::OBSERVACIONES[$i % 4],
                ],
            );

            if (! $pedido->wasRecentlyCreated) {
                continue;
            }

            $pedido->forceFill(['created_at' => $creado, 'updated_at' => $creado])->save();

            $pedido->lineas()->createMany([
                ['producto' => self::PRODUCTOS[$i % 8], 'cantidad' => 1 + $i % 3],
                ['producto' => self::PRODUCTOS[($i + 3) % 8], 'cantidad' => 1],
            ]);

            $pedido->historial()->create([
                'usuario_id' => $creador->id,
                'tipo_evento' => TipoEvento::Creacion,
                'estado_anterior' => null,
                'estado_nuevo' => EstadoPedido::Creado,
                'detalle' => 'Pedido creado',
                'canal' => Canal::Web,
                'created_at' => $creado,
            ]);
        }
    }

    /**
     * Reparte los clientes entre los comercios (uno para cada uno, por turnos) y entre las zonas.
     *
     * @param  Collection<int, Comercio>  $comercios
     * @param  Collection<int, Zona>  $zonas
     * @return list<Cliente>
     */
    private function crearClientes($comercios, $zonas): array
    {
        $clientes = [];

        foreach (self::CLIENTES as $indice => [$nombre, $cedula, $telefono, $direccion, $barrio, $indicaciones]) {
            $clientes[] = Cliente::query()->firstOrCreate(
                ['comercio_id' => $comercios[$indice % $comercios->count()]->id, 'cedula' => $cedula],
                [
                    'nombre' => $nombre,
                    'telefono' => $telefono,
                    'correo' => 'demo'.($indice + 1).'@correo.test',
                    'direccion' => $direccion,
                    'barrio' => $barrio,
                    'indicaciones' => $indicaciones,
                    'zona_id' => $zonas[$indice % $zonas->count()]->id,
                ],
            );
        }

        return $clientes;
    }
}
