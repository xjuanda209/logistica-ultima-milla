<?php

use App\Modules\Administracion\Models\Comercio;
use App\Modules\Administracion\Models\Usuario;
use App\Modules\Pedidos\Models\HistorialPedido;
use App\Modules\Pedidos\Models\Pedido;
use App\Shared\Enums\Canal;
use App\Shared\Enums\EstadoPedido;
use App\Shared\Enums\Prioridad;
use App\Shared\Enums\TipoEvento;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->comercio = Comercio::factory()->create();
    $this->pedido = Pedido::factory()->create([
        'comercio_id' => $this->comercio->id,
        'prioridad' => Prioridad::Media,
        'franja_inicio' => now()->addDay()->setTime(10, 0),
        'franja_fin' => now()->addDay()->setTime(11, 0),
        'observaciones' => 'Entregar en portería.',
    ]);
    $this->pedido->lineas()->createMany([
        ['producto' => 'Pan de bono x12', 'cantidad' => 2],
        ['producto' => 'Torta de chocolate', 'cantidad' => 1],
    ]);
    $this->operador = Usuario::factory()->operador()->create();
});

/**
 * Los mismos datos que ya tiene el pedido; lo que se pase reemplaza al valor actual.
 *
 * @param  array<string, mixed>  $cambios
 * @return array<string, mixed>
 */
function edicionPedido(array $cambios = []): array
{
    $pedido = test()->pedido;

    return [
        'prioridad' => 'media',
        'franja_inicio' => $pedido->franja_inicio->toIso8601String(),
        'franja_fin' => $pedido->franja_fin->toIso8601String(),
        'observaciones' => 'Entregar en portería.',
        'lineas' => [
            ['producto' => 'Pan de bono x12', 'cantidad' => 2],
            ['producto' => 'Torta de chocolate', 'cantidad' => 1],
        ],
        ...$cambios,
    ];
}

test('se edita la prioridad, la franja, las observaciones y los productos de un pedido creado', function (string $rol) {
    Sanctum::actingAs(Usuario::factory()->{$rol}($rol === 'comercio' ? $this->comercio : null)->create());
    $inicio = now()->addDays(3)->setTime(14, 0);

    $this->putJson("/api/pedidos/{$this->pedido->id}", edicionPedido([
        'prioridad' => 'alta',
        'franja_inicio' => $inicio->toIso8601String(),
        'franja_fin' => $inicio->copy()->addHours(2)->toIso8601String(),
        'observaciones' => 'Llamar antes de llegar.',
        'lineas' => [['producto' => 'Galletas', 'cantidad' => 5]],
    ]))
        ->assertOk()
        ->assertJsonPath('data.prioridad', 'alta')
        ->assertJsonPath('data.observaciones', 'Llamar antes de llegar.')
        ->assertJsonPath('data.estado', 'creado')
        ->assertJsonCount(1, 'data.lineas')
        ->assertJsonPath('data.lineas.0.producto', 'Galletas')
        ->assertJsonPath('data.lineas.0.cantidad', 5);

    $pedido = $this->pedido->fresh();

    expect($pedido->prioridad)->toBe(Prioridad::Alta)
        ->and($pedido->franja_inicio->equalTo($inicio))->toBeTrue()
        ->and($pedido->franja_fin->equalTo($inicio->copy()->addHours(2)))->toBeTrue()
        ->and($pedido->lineas()->count())->toBe(1);
})->with(['administrador', 'operador', 'comercio']);

test('editar registra el evento de edición con los campos cambiados, su valor anterior y el nuevo', function () {
    Sanctum::actingAs($this->operador);

    $this->putJson("/api/pedidos/{$this->pedido->id}", edicionPedido([
        'prioridad' => 'alta',
        'lineas' => [
            ['producto' => 'Pan de bono x12', 'cantidad' => 3],
            ['producto' => 'Torta de chocolate', 'cantidad' => 1],
        ],
    ]), ['X-Canal' => 'web'])->assertOk();

    $evento = HistorialPedido::query()->where('pedido_id', $this->pedido->id)->sole();

    expect($evento->tipo_evento)->toBe(TipoEvento::Edicion)
        ->and($evento->usuario_id)->toBe($this->operador->id)
        ->and($evento->canal)->toBe(Canal::Web)
        ->and($evento->estado_anterior)->toBeNull()
        ->and($evento->estado_nuevo)->toBeNull()
        ->and(json_decode($evento->detalle, true))->toBe(['cambios' => [
            ['campo' => 'prioridad', 'anterior' => 'media', 'nuevo' => 'alta'],
            [
                'campo' => 'lineas',
                'anterior' => 'Pan de bono x12 × 2, Torta de chocolate × 1',
                'nuevo' => 'Pan de bono x12 × 3, Torta de chocolate × 1',
            ],
        ]]);

    $this->getJson("/api/pedidos/{$this->pedido->id}")
        ->assertOk()
        ->assertJsonPath('data.historial.0.tipo_evento', 'edicion')
        ->assertJsonPath('data.historial.0.cambios.0.campo', 'prioridad')
        ->assertJsonPath('data.historial.0.cambios.0.anterior', 'media')
        ->assertJsonPath('data.historial.0.cambios.0.nuevo', 'alta');
});

test('el evento de edición guarda las fechas de la franja en UTC', function () {
    Sanctum::actingAs($this->operador);
    $anterior = $this->pedido->franja_fin->copy();
    $nueva = $anterior->copy()->addHour();

    $this->putJson("/api/pedidos/{$this->pedido->id}", edicionPedido([
        'franja_fin' => $nueva->copy()->setTimezone('America/Bogota')->toIso8601String(),
    ]))->assertOk();

    $evento = HistorialPedido::query()->where('pedido_id', $this->pedido->id)->sole();

    expect($evento->cambios())->toBe([[
        'campo' => 'franja_fin',
        'anterior' => $anterior->utc()->toIso8601ZuluString(),
        'nuevo' => $nueva->utc()->toIso8601ZuluString(),
    ]]);
});

test('guardar sin cambiar nada no registra ningún evento', function () {
    Sanctum::actingAs($this->operador);
    $modificado = $this->pedido->updated_at;

    $this->travel(5)->minutes();
    $this->putJson("/api/pedidos/{$this->pedido->id}", edicionPedido())->assertOk();

    expect(HistorialPedido::query()->count())->toBe(0)
        ->and($this->pedido->fresh()->updated_at->equalTo($modificado))->toBeTrue();
});

test('un pedido que ya no está en creado no se puede editar y se informa el motivo', function (EstadoPedido $estado) {
    Sanctum::actingAs($this->operador);
    $this->pedido->update(['estado' => $estado]);

    $this->putJson("/api/pedidos/{$this->pedido->id}", edicionPedido(['prioridad' => 'alta', 'lineas' => [
        ['producto' => 'Otro', 'cantidad' => 9],
    ]]))
        ->assertConflict()
        ->assertJsonPath('error.codigo', 'PEDIDO_NO_EDITABLE')
        ->assertJsonPath('error.mensaje', 'El pedido solo puede editarse mientras está en estado «Creado».');

    expect($this->pedido->fresh()->prioridad)->toBe(Prioridad::Media)
        ->and($this->pedido->lineas()->count())->toBe(2)
        ->and(HistorialPedido::query()->count())->toBe(0);
})->with([
    EstadoPedido::Asignado, EstadoPedido::Recogido, EstadoPedido::EnRuta,
    EstadoPedido::Entregado, EstadoPedido::NoEntregado, EstadoPedido::Cancelado,
]);

test('al editar no se puede cambiar el comercio, el cliente, el número ni el estado', function () {
    Sanctum::actingAs($this->operador);
    $otroComercio = Comercio::factory()->create();

    $this->putJson("/api/pedidos/{$this->pedido->id}", edicionPedido([
        'prioridad' => 'baja',
        'comercio_id' => $otroComercio->id,
        'cliente_id' => 9999,
        'numero' => 'PED-9999',
        'estado' => 'entregado',
        'direccion' => 'Otra dirección',
    ]))->assertOk();

    $pedido = $this->pedido->fresh();

    expect($pedido->prioridad)->toBe(Prioridad::Baja)
        ->and($pedido->comercio_id)->toBe($this->comercio->id)
        ->and($pedido->cliente_id)->toBe($this->pedido->cliente_id)
        ->and($pedido->numero)->toBe($this->pedido->numero)
        ->and($pedido->estado)->toBe(EstadoPedido::Creado)
        ->and($pedido->direccion)->toBe($this->pedido->direccion);
});

test('editar valida las mismas reglas que crear', function () {
    Sanctum::actingAs($this->operador);

    $respuesta = $this->putJson("/api/pedidos/{$this->pedido->id}", edicionPedido([
        'prioridad' => 'urgente',
        'franja_inicio' => now()->subHour()->toIso8601String(),
        'franja_fin' => now()->subHours(2)->toIso8601String(),
        'lineas' => [['producto' => 'Pan', 'cantidad' => 0]],
    ]))
        ->assertUnprocessable()
        ->assertJsonPath('error.codigo', 'VALIDACION')
        ->assertJsonPath('error.campos.prioridad.0', 'El valor elegido para prioridad no es válido.')
        ->assertJsonPath('error.campos.franja_inicio.0', 'La franja no puede empezar en el pasado.')
        ->assertJsonPath('error.campos.franja_fin.0', 'La franja debe terminar después de empezar.');

    expect($respuesta->json('error.campos')['lineas.0.cantidad'])->toBe(['Debe ser mayor que 0.'])
        ->and($this->pedido->fresh()->prioridad)->toBe(Prioridad::Media)
        ->and(HistorialPedido::query()->count())->toBe(0);

    $this->putJson("/api/pedidos/{$this->pedido->id}", edicionPedido(['lineas' => []]))
        ->assertUnprocessable()
        ->assertJsonPath('error.campos.lineas.0', 'Agrega al menos un producto.');
});

test('si la franja ya empezó se pueden editar otros campos sin tocar su inicio', function () {
    Sanctum::actingAs($this->operador);
    $this->pedido->update(['franja_inicio' => now()->subHour(), 'franja_fin' => now()->addHour()]);
    $this->pedido->refresh();

    $this->putJson("/api/pedidos/{$this->pedido->id}", edicionPedido(['observaciones' => 'Timbre dañado.']))
        ->assertOk()
        ->assertJsonPath('data.observaciones', 'Timbre dañado.');

    $this->putJson("/api/pedidos/{$this->pedido->id}", edicionPedido([
        'franja_inicio' => now()->subMinutes(30)->toIso8601String(),
    ]))
        ->assertUnprocessable()
        ->assertJsonPath('error.campos.franja_inicio.0', 'La franja no puede empezar en el pasado.');
});

test('un comercio no puede editar el pedido de otro comercio', function () {
    Sanctum::actingAs(Usuario::factory()->comercio()->create());

    $this->putJson("/api/pedidos/{$this->pedido->id}", edicionPedido(['prioridad' => 'alta']))
        ->assertNotFound()
        ->assertJsonPath('error.codigo', 'NO_ENCONTRADO');

    expect($this->pedido->fresh()->prioridad)->toBe(Prioridad::Media)
        ->and(HistorialPedido::query()->count())->toBe(0);
});

test('editar un pedido que no existe responde 404', function () {
    Sanctum::actingAs($this->operador);

    $this->putJson('/api/pedidos/9999', edicionPedido())
        ->assertNotFound()
        ->assertJsonPath('error.codigo', 'NO_ENCONTRADO');
});

test('editar pedidos responde 401 sin token', function () {
    $this->putJson("/api/pedidos/{$this->pedido->id}", [])
        ->assertUnauthorized()
        ->assertJsonPath('error.codigo', 'NO_AUTENTICADO');
});

test('el repartidor no puede editar pedidos, ni siquiera los que tiene asignados', function () {
    $repartidor = Usuario::factory()->repartidor()->create();
    $this->pedido->update(['repartidor_id' => $repartidor->id]);
    Sanctum::actingAs($repartidor);

    $this->putJson("/api/pedidos/{$this->pedido->id}", edicionPedido(['prioridad' => 'alta']))
        ->assertForbidden()
        ->assertJsonPath('error.codigo', 'NO_AUTORIZADO');

    expect($this->pedido->fresh()->prioridad)->toBe(Prioridad::Media);
});
