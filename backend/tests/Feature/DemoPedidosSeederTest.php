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
    $this->seed(DatabaseSeeder::class);
});

test('el seeder de demostración agrega 8 clientes y 12 pedidos creados, sin tocar lo que ya había', function () {
    $clientesAntes = Cliente::query()->count();
    $pedidosAntes = Pedido::query()->count();
    $historialAntes = HistorialPedido::query()->count();

    $this->seed(DemoPedidosSeeder::class);

    $demo = Pedido::query()->where('numero', 'like', 'P-______')->with(['lineas', 'historial', 'cliente'])->get();

    expect(Cliente::query()->count())->toBe($clientesAntes + 8)
        ->and(Pedido::query()->count())->toBe($pedidosAntes + 12)
        ->and(HistorialPedido::query()->count())->toBe($historialAntes + 12)
        ->and($demo)->toHaveCount(12)
        ->and($demo->pluck('numero')->sort()->values()->all())
        ->toBe(array_map(fn (int $i): string => sprintf('P-%06d', $i), range(1, 12)));

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

test('el seeder de demostración es idempotente: repetirlo no duplica ni modifica nada', function () {
    $this->seed(DemoPedidosSeeder::class);

    $pedido = Pedido::query()->where('numero', 'P-000003')->firstOrFail();
    $pedido->update(['observaciones' => 'Cambiado a mano']);
    $conteos = fn (): array => [
        Cliente::query()->count(), Pedido::query()->count(), LineaPedido::query()->count(), HistorialPedido::query()->count(),
    ];
    $antes = $conteos();

    $this->seed(DemoPedidosSeeder::class);

    expect($conteos())->toBe($antes)
        ->and($pedido->fresh()->observaciones)->toBe('Cambiado a mano');
});

test('después del seeder de demostración el siguiente pedido es el P-000013', function () {
    $this->seed(DemoPedidosSeeder::class);
    Sanctum::actingAs(Usuario::query()->where('correo', 'comercio@logistica.test')->firstOrFail());
    $pedido = Pedido::query()->where('numero', 'P-000001')->firstOrFail();

    expect(Pedido::siguienteNumero())->toBe('P-000013');

    $this->getJson('/api/pedidos?numero=P-0000')->assertOk()->assertJsonPath('meta.total', Pedido::query()
        ->where('numero', 'like', 'P-______')
        ->where('comercio_id', Usuario::query()->where('correo', 'comercio@logistica.test')->value('comercio_id'))
        ->count());

    expect($pedido->estado)->toBe(EstadoPedido::Creado);
});
