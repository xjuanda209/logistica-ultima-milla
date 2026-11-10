<?php

use App\Modules\Administracion\Models\Comercio;
use App\Modules\Administracion\Models\Configuracion;
use App\Modules\Administracion\Models\Usuario;
use App\Modules\Administracion\Models\Zona;
use App\Modules\Pedidos\Models\Cliente;
use App\Modules\Pedidos\Models\HistorialPedido;
use App\Modules\Pedidos\Models\Pedido;
use App\Shared\Enums\EstadoPedido;
use App\Shared\Enums\Rol;
use Database\Seeders\DatabaseSeeder;

test('los seeders crean los datos ficticios esperados y no duplican al repetirse', function () {
    $this->seed(DatabaseSeeder::class);
    $this->seed(DatabaseSeeder::class);

    expect(Zona::query()->count())->toBe(4)
        ->and(Comercio::query()->count())->toBe(2)
        ->and(Configuracion::query()->count())->toBe(1)
        ->and(Cliente::query()->count())->toBe(10)
        ->and(Pedido::query()->count())->toBe(20)
        ->and(Usuario::query()->count())->toBe(5);

    foreach (Rol::cases() as $rol) {
        expect(Usuario::query()->where('rol', $rol)->exists())->toBeTrue();
    }

    foreach (EstadoPedido::cases() as $estado) {
        expect(Pedido::query()->where('estado', $estado)->exists())->toBeTrue();
    }

    expect(Pedido::query()->doesntHave('lineas')->count())->toBe(0)
        ->and(Pedido::query()->doesntHave('historial')->count())->toBe(0)
        ->and(Pedido::query()->whereColumn('pedidos.comercio_id', '!=', 'clientes.comercio_id')
            ->join('clientes', 'clientes.id', '=', 'pedidos.cliente_id')->count())->toBe(0);

    $entregado = Pedido::query()->where('estado', EstadoPedido::Entregado)->firstOrFail();

    expect($entregado->historial->last()->estado_nuevo)->toBe(EstadoPedido::Entregado)
        ->and($entregado->historial)->toHaveCount(5)
        ->and(HistorialPedido::query()->count())->toBeGreaterThan(20);
});

test('los usuarios sembrados pueden iniciar sesión con la contraseña de prueba', function () {
    $this->seed(DatabaseSeeder::class);

    $this->postJson('/api/login', ['correo' => 'repartidor@logistica.test', 'password' => 'password'])
        ->assertOk()
        ->assertJsonPath('usuario.rol', 'repartidor');
});
