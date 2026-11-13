<?php

use App\Modules\Administracion\Models\Usuario;
use App\Modules\Despacho\Models\Evidencia;
use App\Modules\Incidencias\Models\Incidencia;
use App\Modules\Pedidos\Models\HistorialPedido;
use App\Modules\Pedidos\Models\LineaPedido;
use App\Modules\Pedidos\Models\Pedido;
use App\Shared\Enums\Disponibilidad;
use App\Shared\Enums\EstadoIncidencia;
use App\Shared\Enums\EstadoPedido;
use App\Shared\Enums\Rol;
use App\Shared\Enums\TipoEvento;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoOperacionSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    // Como en Neon: 5 usuarios, 10 clientes y 20 pedidos.
    $this->seed(DatabaseSeeder::class);
});

/**
 * Pedidos que creó DemoOperacionSeeder (se reconocen por el detalle de su evento de creación).
 */
function pedidosDeLaOperacion()
{
    return Pedido::query()
        ->whereHas('historial', fn ($historial) => $historial->where('detalle', DemoOperacionSeeder::MARCA))
        ->with(['lineas', 'historial', 'cliente', 'repartidor', 'incidencias'])
        ->orderBy('id')
        ->get();
}

test('el seeder de la operación crea 4 repartidores en zonas y con disponibilidad distintas', function () {
    $this->seed(DemoOperacionSeeder::class);

    $nuevos = Usuario::query()->where('correo', 'like', 'repartidor_@logistica.test')->with('zona')->orderBy('correo')->get();

    expect($nuevos->pluck('correo')->all())->toBe([
        'repartidor2@logistica.test', 'repartidor3@logistica.test', 'repartidor4@logistica.test', 'repartidor5@logistica.test',
    ])
        ->and($nuevos->pluck('zona.nombre')->all())->toBe(['Norte', 'Sur', 'Occidente', 'Centro'])
        ->and($nuevos->pluck('disponibilidad')->all())->toBe([
            Disponibilidad::Disponible, Disponibilidad::NoDisponible, Disponibilidad::Disponible, Disponibilidad::NoDisponible,
        ])
        ->and($nuevos->every(fn (Usuario $usuario): bool => $usuario->rol === Rol::Repartidor && $usuario->activo))->toBeTrue();

    $this->postJson('/api/login', ['correo' => 'repartidor3@logistica.test', 'password' => 'password'])
        ->assertOk()
        ->assertJsonPath('usuario.rol', 'repartidor');
});

test('el seeder de la operación agrega 15 pedidos en estados variados, sin tocar lo que ya había', function () {
    $anteriores = Pedido::query()->orderBy('id')->get()->toArray();

    $this->seed(DemoOperacionSeeder::class);

    $demo = pedidosDeLaOperacion();

    expect($demo)->toHaveCount(15)
        ->and(Pedido::query()->count())->toBe(count($anteriores) + 15)
        ->and(Pedido::query()->whereKey(array_column($anteriores, 'id'))->orderBy('id')->get()->toArray())->toBe($anteriores)
        ->and($demo->pluck('numero')->all())->toBe(array_map(fn (int $i): string => sprintf('PED-%04d', $i), range(21, 35)))
        ->and($demo->countBy(fn (Pedido $pedido): string => $pedido->estado->value)->all())->toBe([
            'creado' => 2, 'asignado' => 4, 'recogido' => 1, 'en_ruta' => 2, 'entregado' => 3, 'no_entregado' => 2, 'cancelado' => 1,
        ]);

    // 3 quedan asignados al repartidor del móvil.
    $delMovil = $demo->where('estado', EstadoPedido::Asignado)->where('repartidor.correo', 'repartidor@logistica.test');
    expect($delMovil)->toHaveCount(3);

    foreach ($demo as $pedido) {
        $sinRepartidor = in_array($pedido->estado, [EstadoPedido::Creado, EstadoPedido::Cancelado], true);

        expect($pedido->lineas)->toHaveCount(2)
            ->and($pedido->cliente->comercio_id)->toBe($pedido->comercio_id)
            ->and($pedido->zona_id)->toBe($pedido->cliente->zona_id)
            ->and($pedido->repartidor === null)->toBe($sinRepartidor)
            ->and($pedido->franja_fin->greaterThan($pedido->franja_inicio))->toBeTrue();

        // Solo se asigna a repartidores de la zona del pedido.
        if ($pedido->repartidor !== null) {
            expect($pedido->repartidor->zona_id)->toBe($pedido->zona_id);
        }
    }
});

test('el historial de los pedidos de la operación es coherente con su estado y sus horas', function () {
    $this->seed(DemoOperacionSeeder::class);

    foreach (pedidosDeLaOperacion() as $pedido) {
        $eventos = $pedido->historial;
        $horas = $eventos->map(fn (HistorialPedido $evento): int => $evento->created_at->getTimestamp())->all();
        $ordenadas = $horas;
        sort($ordenadas);

        expect($eventos[0]->tipo_evento)->toBe(TipoEvento::Creacion)
            ->and($eventos[0]->created_at->equalTo($pedido->created_at))->toBeTrue()
            ->and($eventos->last()->estado_nuevo)->toBe($pedido->estado)
            ->and($horas)->toBe($ordenadas)
            ->and(count(array_unique($horas)))->toBe(count($horas))
            ->and($eventos->last()->created_at->isPast())->toBeTrue()
            ->and($pedido->updated_at->equalTo($eventos->last()->created_at))->toBeTrue();

        // Cada paso es una transición que la máquina de estados permite.
        foreach ($eventos->skip(1) as $evento) {
            expect($evento->estado_anterior->puedePasarA($evento->estado_nuevo))->toBeTrue();
        }
    }
});

test('los pedidos no entregados de la operación tienen incidencia y no se crean evidencias sin archivo', function () {
    $incidenciasAntes = Incidencia::query()->count();

    $this->seed(DemoOperacionSeeder::class);

    $demo = pedidosDeLaOperacion();

    foreach ($demo->where('estado', EstadoPedido::NoEntregado) as $pedido) {
        expect($pedido->incidencias)->toHaveCount(1)
            ->and($pedido->incidencias[0]->usuario_id)->toBe($pedido->repartidor_id)
            ->and($pedido->incidencias[0]->created_at->between($pedido->created_at, $pedido->updated_at))->toBeTrue();
    }

    // Además hay un pedido en ruta con una incidencia abierta (el ⚠ del panel).
    $enRuta = $demo->where('estado', EstadoPedido::EnRuta)->filter(fn (Pedido $pedido): bool => $pedido->incidencias->isNotEmpty());

    expect(Incidencia::query()->count())->toBe($incidenciasAntes + 3)
        ->and($enRuta)->toHaveCount(1)
        ->and($enRuta->first()->incidencias[0]->estado)->toBe(EstadoIncidencia::Abierta)
        ->and(Evidencia::query()->count())->toBe(0);
});

test('el seeder de la operación es idempotente: repetirlo no duplica ni modifica nada', function () {
    $this->seed(DemoOperacionSeeder::class);

    Usuario::query()->where('correo', 'repartidor2@logistica.test')->update(['disponibilidad' => Disponibilidad::NoDisponible]);
    $conteos = fn (): array => [
        Usuario::query()->count(), Pedido::query()->count(), LineaPedido::query()->count(),
        HistorialPedido::query()->count(), Incidencia::query()->count(),
    ];
    $antes = $conteos();

    $this->seed(DemoOperacionSeeder::class);
    $this->seed(DemoOperacionSeeder::class);

    expect($conteos())->toBe($antes)
        ->and($antes[0])->toBe(9)
        ->and(Usuario::query()->where('correo', 'repartidor2@logistica.test')->value('disponibilidad'))->toBe(Disponibilidad::NoDisponible);
});

test('el panel muestra indicadores con los datos de la operación', function () {
    $this->seed(DemoOperacionSeeder::class);
    Sanctum::actingAs(Usuario::query()->where('correo', 'operador@logistica.test')->firstOrFail());

    $respuesta = $this->getJson('/api/panel/indicadores?desde='.now()->subDays(6)->toIso8601ZuluString())->assertOk();

    expect($respuesta->json('data.porcentaje_exito'))->not->toBeNull()
        ->and($respuesta->json('data.tiempo_promedio_minutos'))->toBeGreaterThan(0)
        ->and($respuesta->json('data.incidencias.por_repartidor'))->not->toBeEmpty();
});
