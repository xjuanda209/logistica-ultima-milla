<?php

use App\Modules\Administracion\Models\Comercio;
use App\Modules\Administracion\Models\Usuario;
use App\Modules\Pedidos\Models\Cliente;
use App\Modules\Pedidos\Models\HistorialPedido;
use App\Modules\Pedidos\Models\LineaPedido;
use App\Modules\Pedidos\Models\Pedido;
use App\Shared\Enums\EstadoPedido;
use App\Shared\Enums\TipoEvento;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoPedidosSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    // Como en Neon: 10 clientes y 20 pedidos, del PED-0001 al PED-0020.
    $this->seed(DatabaseSeeder::class);
});

/**
 * Pedidos de los clientes de demostración (los que tienen correo demoN@correo.test).
 */
function pedidosDeDemostracion()
{
    return Pedido::query()
        ->whereHas('cliente', fn ($clientes) => $clientes->where('correo', 'like', 'demo%@correo.test'))
        ->with(['lineas', 'historial', 'cliente'])
        ->orderBy('id')
        ->get();
}

test('el seeder de demostración agrega 8 clientes y 12 pedidos creados, sin tocar lo que ya había', function () {
    $anteriores = Pedido::query()->orderBy('id')->get()->toArray();
    $clientesAntes = Cliente::query()->count();
    $historialAntes = HistorialPedido::query()->count();

    $this->seed(DemoPedidosSeeder::class);

    $demo = pedidosDeDemostracion();

    expect(Cliente::query()->count())->toBe($clientesAntes + 8)
        ->and(Pedido::query()->count())->toBe(count($anteriores) + 12)
        ->and(HistorialPedido::query()->count())->toBe($historialAntes + 12)
        ->and($demo)->toHaveCount(12)
        ->and(Pedido::query()->whereKey(array_column($anteriores, 'id'))->orderBy('id')->get()->toArray())->toBe($anteriores);

    foreach ($demo as $pedido) {
        expect($pedido->estado)->toBe(EstadoPedido::Creado)
            ->and($pedido->repartidor_id)->toBeNull()
            ->and($pedido->lineas)->toHaveCount(2)
            ->and($pedido->historial)->toHaveCount(1)
            ->and($pedido->historial[0]->tipo_evento)->toBe(TipoEvento::Creacion)
            ->and($pedido->historial[0]->usuario_id)->toBe($pedido->creado_por)
            ->and($pedido->cliente->comercio_id)->toBe($pedido->comercio_id)
            ->and($pedido->direccion)->toBe($pedido->cliente->direccion)
            ->and($pedido->zona_id)->toBe($pedido->cliente->zona_id)
            ->and($pedido->franja_inicio->isFuture())->toBeTrue()
            ->and($pedido->franja_fin->greaterThan($pedido->franja_inicio))->toBeTrue();
    }

    // Repartidos entre los comercios.
    foreach (Comercio::query()->pluck('id') as $comercioId) {
        expect($demo->where('comercio_id', $comercioId)->count())->toBeGreaterThan(0);
    }
});

test('los pedidos de demostración continúan la secuencia después del último número que exista', function () {
    expect(Pedido::query()->max('numero'))->toBe('PED-0020');

    $this->seed(DemoPedidosSeeder::class);

    expect(pedidosDeDemostracion()->pluck('numero')->all())
        ->toBe(array_map(fn (int $i): string => sprintf('PED-%04d', $i), range(21, 32)))
        ->and(Pedido::query()->distinct()->count('numero'))->toBe(Pedido::query()->count())
        ->and(Pedido::siguienteNumero())->toBe('PED-0033');
});

test('si ya hay pedidos creados desde la aplicación, los de demostración siguen después de ellos', function () {
    Pedido::factory()->create(['numero' => 'PED-0021']);
    Pedido::factory()->create(['numero' => 'PED-0045']);

    $this->seed(DemoPedidosSeeder::class);

    expect(pedidosDeDemostracion()->pluck('numero')->all())
        ->toBe(array_map(fn (int $i): string => sprintf('PED-%04d', $i), range(46, 57)));
});

test('el seeder de demostración es idempotente: repetirlo no duplica ni modifica nada', function () {
    $this->seed(DemoPedidosSeeder::class);

    $pedido = pedidosDeDemostracion()->first();
    $pedido->update(['observaciones' => 'Cambiado a mano']);
    // Entre una ejecución y otra alguien crea un pedido: la secuencia avanza y no debe importar.
    Pedido::factory()->create(['numero' => Pedido::siguienteNumero()]);
    $conteos = fn (): array => [
        Cliente::query()->count(), Pedido::query()->count(), LineaPedido::query()->count(), HistorialPedido::query()->count(),
    ];
    $antes = $conteos();

    $this->seed(DemoPedidosSeeder::class);
    $this->seed(DemoPedidosSeeder::class);

    expect($conteos())->toBe($antes)
        ->and(pedidosDeDemostracion())->toHaveCount(12)
        ->and($pedido->fresh()->observaciones)->toBe('Cambiado a mano');
});

test('si los clientes de demostración ya tienen pedidos, el seeder no crea más', function () {
    $this->seed(DemoPedidosSeeder::class);

    // Aunque se hayan cancelado o entregado casi todos, con uno que exista no se vuelve a sembrar.
    $demo = pedidosDeDemostracion();
    $demo->skip(1)->each(function (Pedido $pedido): void {
        $pedido->lineas()->delete();
        $pedido->historial()->delete();
        $pedido->delete();
    });

    $this->seed(DemoPedidosSeeder::class);

    expect(pedidosDeDemostracion())->toHaveCount(1);
});

test('el comercio ve en la lista sus pedidos de demostración', function () {
    $this->seed(DemoPedidosSeeder::class);
    $usuario = Usuario::query()->where('correo', 'comercio@logistica.test')->firstOrFail();
    Sanctum::actingAs($usuario);

    $propios = pedidosDeDemostracion()->where('comercio_id', $usuario->comercio_id);

    $this->getJson('/api/pedidos?estado=creado&numero='.$propios->first()->numero)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.estado', 'creado');
});
