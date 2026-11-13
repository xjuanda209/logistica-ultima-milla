<?php

use App\Modules\Administracion\Models\Usuario;
use App\Modules\Pedidos\Models\HistorialPedido;
use App\Modules\Pedidos\Models\Pedido;
use App\Shared\Enums\Canal;
use App\Shared\Enums\EstadoPedido;
use App\Shared\Enums\TipoEvento;
use App\Shared\Errores\ErrorApi;

/** Las únicas transiciones permitidas (docs/PLAN.md, Parte A). */
const TRANSICIONES_PERMITIDAS = [
    'creado' => ['asignado', 'cancelado'],
    'asignado' => ['asignado', 'recogido', 'cancelado'],
    'recogido' => ['en_ruta'],
    'en_ruta' => ['entregado', 'no_entregado'],
    'entregado' => [],
    'no_entregado' => [],
    'cancelado' => [],
];

dataset('transiciones permitidas', function () {
    foreach (TRANSICIONES_PERMITIDAS as $desde => $destinos) {
        foreach ($destinos as $hasta) {
            yield "{$desde} → {$hasta}" => [$desde, $hasta];
        }
    }
});

dataset('transiciones no permitidas', function () {
    foreach (EstadoPedido::cases() as $desde) {
        foreach (EstadoPedido::cases() as $hasta) {
            if (! in_array($hasta->value, TRANSICIONES_PERMITIDAS[$desde->value], true)) {
                yield "{$desde->value} → {$hasta->value}" => [$desde->value, $hasta->value];
            }
        }
    }
});

test('una transición permitida cambia el estado y deja el evento en el historial', function (string $desde, string $hasta) {
    $usuario = Usuario::factory()->operador()->create();
    $pedido = Pedido::factory()->create(['estado' => $desde]);

    $pedido->cambiarEstado(EstadoPedido::from($hasta), $usuario, Canal::Movil, 'Detalle de prueba');

    expect($pedido->fresh()->estado->value)->toBe($hasta);

    $evento = HistorialPedido::query()->where('pedido_id', $pedido->id)->sole();

    expect($evento->tipo_evento)->toBe(TipoEvento::CambioEstado)
        ->and($evento->estado_anterior->value)->toBe($desde)
        ->and($evento->estado_nuevo->value)->toBe($hasta)
        ->and($evento->usuario_id)->toBe($usuario->id)
        ->and($evento->canal)->toBe(Canal::Movil)
        ->and($evento->detalle)->toBe('Detalle de prueba');
})->with('transiciones permitidas');

test('una transición no permitida se rechaza con un error claro y no cambia nada', function (string $desde, string $hasta) {
    $usuario = Usuario::factory()->operador()->create();
    $pedido = Pedido::factory()->create(['estado' => $desde]);

    try {
        $pedido->cambiarEstado(EstadoPedido::from($hasta), $usuario, Canal::Web);
        $this->fail('La transición debió rechazarse.');
    } catch (ErrorApi $error) {
        expect($error->codigo)->toBe('TRANSICION_NO_PERMITIDA')
            ->and($error->estadoHttp)->toBe(409)
            ->and($error->getMessage())->toContain('«'.EstadoPedido::from($desde)->etiqueta().'»');
    }

    expect($pedido->fresh()->estado->value)->toBe($desde)
        ->and(HistorialPedido::query()->count())->toBe(0);
})->with('transiciones no permitidas');

test('entregado, no entregado y cancelado son estados finales', function () {
    $finales = array_values(array_filter(EstadoPedido::cases(), fn (EstadoPedido $estado): bool => $estado->esFinal()));

    expect($finales)->toBe([EstadoPedido::Entregado, EstadoPedido::NoEntregado, EstadoPedido::Cancelado]);
});
