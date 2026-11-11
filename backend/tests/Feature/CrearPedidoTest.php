<?php

use App\Modules\Administracion\Models\Comercio;
use App\Modules\Administracion\Models\Usuario;
use App\Modules\Administracion\Models\Zona;
use App\Modules\Pedidos\Models\Cliente;
use App\Modules\Pedidos\Models\HistorialPedido;
use App\Modules\Pedidos\Models\LineaPedido;
use App\Modules\Pedidos\Models\Pedido;
use App\Shared\Enums\Canal;
use App\Shared\Enums\EstadoPedido;
use App\Shared\Enums\TipoEvento;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->comercio = Comercio::factory()->create(['nombre' => 'Panadería La Espiga']);
    $this->zona = Zona::factory()->create(['nombre' => 'Centro']);
    $this->cliente = Cliente::factory()->create([
        'comercio_id' => $this->comercio->id,
        'nombre' => 'Laura Gómez',
        'direccion' => 'Cra 8 # 20-15, apto 301',
        'barrio' => 'Centro',
        'indicaciones' => 'Portería, torre B',
        'zona_id' => $this->zona->id,
    ]);
    $this->operador = Usuario::factory()->operador()->create();
});

/**
 * Datos válidos para crear un pedido; lo que se pase reemplaza al valor por defecto.
 *
 * @param  array<string, mixed>  $cambios
 * @return array<string, mixed>
 */
function datosPedido(array $cambios = []): array
{
    return [
        'comercio_id' => test()->comercio->id,
        'cliente_id' => test()->cliente->id,
        'prioridad' => 'media',
        'franja_inicio' => now()->addDay()->setTime(10, 0)->toIso8601String(),
        'franja_fin' => now()->addDay()->setTime(11, 0)->toIso8601String(),
        'observaciones' => 'Entregar en portería, torre B.',
        'lineas' => [
            ['producto' => 'Pan de bono x12', 'cantidad' => 2],
            ['producto' => 'Torta de chocolate', 'cantidad' => 1],
        ],
        ...$cambios,
    ];
}

test('se crea un pedido en estado creado con la dirección copiada del cliente', function (string $rol) {
    $usuario = Usuario::factory()->{$rol}()->create();
    Sanctum::actingAs($usuario);

    $respuesta = $this->postJson('/api/pedidos', datosPedido())
        ->assertCreated()
        ->assertJsonPath('data.numero', 'PED-0001')
        ->assertJsonPath('data.estado', 'creado')
        ->assertJsonPath('data.prioridad', 'media')
        ->assertJsonPath('data.direccion', 'Cra 8 # 20-15, apto 301')
        ->assertJsonPath('data.barrio', 'Centro')
        ->assertJsonPath('data.indicaciones', 'Portería, torre B')
        ->assertJsonPath('data.zona.nombre', 'Centro')
        ->assertJsonPath('data.cliente.nombre', 'Laura Gómez')
        ->assertJsonPath('data.comercio.nombre', 'Panadería La Espiga')
        ->assertJsonPath('data.repartidor', null)
        ->assertJsonPath('data.lineas.0.producto', 'Pan de bono x12')
        ->assertJsonPath('data.lineas.0.cantidad', 2)
        ->assertJsonCount(2, 'data.lineas');

    $pedido = Pedido::query()->findOrFail($respuesta->json('data.id'));

    expect($pedido->creado_por)->toBe($usuario->id)
        ->and($pedido->estado)->toBe(EstadoPedido::Creado)
        ->and($pedido->repartidor_id)->toBeNull()
        ->and($pedido->zona_id)->toBe($this->zona->id)
        ->and($pedido->observaciones)->toBe('Entregar en portería, torre B.')
        ->and($pedido->franja_inicio->equalTo(now()->addDay()->setTime(10, 0)))->toBeTrue();
})->with(['administrador', 'operador']);

test('al crear un pedido se registra el evento de creación con el usuario y el canal', function (?string $encabezado, Canal $canal) {
    Sanctum::actingAs($this->operador);

    $respuesta = $this->postJson('/api/pedidos', datosPedido(), $encabezado ? ['X-Canal' => $encabezado] : [])
        ->assertCreated()
        ->assertJsonCount(1, 'data.historial')
        ->assertJsonPath('data.historial.0.tipo_evento', 'creacion')
        ->assertJsonPath('data.historial.0.usuario', $this->operador->nombre);

    $evento = HistorialPedido::query()->where('pedido_id', $respuesta->json('data.id'))->sole();

    expect($evento->tipo_evento)->toBe(TipoEvento::Creacion)
        ->and($evento->usuario_id)->toBe($this->operador->id)
        ->and($evento->estado_anterior)->toBeNull()
        ->and($evento->estado_nuevo)->toBe(EstadoPedido::Creado)
        ->and($evento->canal)->toBe($canal);
})->with([
    'sin encabezado es web' => [null, Canal::Web],
    'web' => ['web', Canal::Web],
    'móvil' => ['movil', Canal::Movil],
]);

test('el primer pedido del sistema es el PED-0001', function () {
    Sanctum::actingAs($this->operador);

    $this->postJson('/api/pedidos', datosPedido())->assertCreated()->assertJsonPath('data.numero', 'PED-0001');
    $this->postJson('/api/pedidos', datosPedido())->assertCreated()->assertJsonPath('data.numero', 'PED-0002');
});

test('el número lo genera el sistema y continúa después del más alto que exista', function () {
    Sanctum::actingAs($this->operador);
    Pedido::factory()->create(['numero' => 'PED-0020']);
    Pedido::factory()->create(['numero' => 'PED-0007']);

    $this->postJson('/api/pedidos', datosPedido(['numero' => 'MI-NUMERO']))
        ->assertCreated()
        ->assertJsonPath('data.numero', 'PED-0021');

    $this->postJson('/api/pedidos', datosPedido())->assertCreated()->assertJsonPath('data.numero', 'PED-0022');

    expect(Pedido::query()->distinct()->count('numero'))->toBe(Pedido::query()->count());
});

test('el consecutivo sigue creciendo después de PED-9999 sin chocar con números existentes', function () {
    Sanctum::actingAs($this->operador);
    Pedido::factory()->create(['numero' => 'PED-9999']);
    Pedido::factory()->create(['numero' => 'PED-0500']);

    $this->postJson('/api/pedidos', datosPedido())->assertCreated()->assertJsonPath('data.numero', 'PED-10000');
    $this->postJson('/api/pedidos', datosPedido())->assertCreated()->assertJsonPath('data.numero', 'PED-10001');
});

test('los números que no son consecutivos del sistema no alteran la secuencia', function (string $ajeno) {
    Sanctum::actingAs($this->operador);
    Pedido::factory()->create(['numero' => 'PED-0020']);
    Pedido::factory()->create(['numero' => $ajeno]);

    $this->postJson('/api/pedidos', datosPedido())->assertCreated()->assertJsonPath('data.numero', 'PED-0021');
})->with(['P-000041', 'PED-MANUAL', 'PED-0099X', 'OTRO-5000']);

test('el pedido conserva su dirección aunque después cambie la del cliente', function () {
    Sanctum::actingAs($this->operador);
    $id = $this->postJson('/api/pedidos', datosPedido())->assertCreated()->json('data.id');

    $this->cliente->update(['direccion' => 'Calle 14 # 5-40', 'barrio' => 'Cuba']);

    $this->getJson("/api/pedidos/{$id}")
        ->assertOk()
        ->assertJsonPath('data.direccion', 'Cra 8 # 20-15, apto 301')
        ->assertJsonPath('data.barrio', 'Centro');
});

test('al comercio se le fuerza su propio comercio aunque la petición diga otro', function () {
    $otroComercio = Comercio::factory()->create();
    Sanctum::actingAs(Usuario::factory()->comercio($this->comercio)->create());

    $this->postJson('/api/pedidos', datosPedido(['comercio_id' => $otroComercio->id]))
        ->assertCreated()
        ->assertJsonPath('data.comercio_id', $this->comercio->id);

    expect(Pedido::query()->where('comercio_id', $otroComercio->id)->exists())->toBeFalse();
});

test('un comercio no puede crear pedidos para el cliente de otro comercio', function () {
    $clienteAjeno = Cliente::factory()->create();
    Sanctum::actingAs(Usuario::factory()->comercio($this->comercio)->create());

    $this->postJson('/api/pedidos', datosPedido([
        'comercio_id' => $clienteAjeno->comercio_id,
        'cliente_id' => $clienteAjeno->id,
    ]))
        ->assertUnprocessable()
        ->assertJsonPath('error.codigo', 'VALIDACION')
        ->assertJsonPath('error.campos.cliente_id.0', 'El cliente elegido no existe en este comercio.');

    expect(Pedido::query()->count())->toBe(0);
});

test('el cliente del pedido debe pertenecer al comercio elegido', function () {
    Sanctum::actingAs($this->operador);
    $otroComercio = Comercio::factory()->create();

    $this->postJson('/api/pedidos', datosPedido(['comercio_id' => $otroComercio->id]))
        ->assertUnprocessable()
        ->assertJsonPath('error.campos.cliente_id.0', 'El cliente elegido no existe en este comercio.');

    expect(Pedido::query()->count())->toBe(0);
});

test('no se crean pedidos para un comercio desactivado', function (string $rol) {
    $this->comercio->update(['activo' => false]);
    Sanctum::actingAs(Usuario::factory()->{$rol}($rol === 'comercio' ? $this->comercio : null)->create());

    $this->postJson('/api/pedidos', datosPedido())
        ->assertConflict()
        ->assertJsonPath('error.codigo', 'COMERCIO_INACTIVO')
        ->assertJsonPath('error.mensaje', 'No se pueden crear pedidos para un comercio desactivado.');

    expect(Pedido::query()->count())->toBe(0)
        ->and(HistorialPedido::query()->count())->toBe(0);
})->with(['operador', 'comercio']);

test('crear un pedido exige sus datos obligatorios', function () {
    Sanctum::actingAs($this->operador);

    $this->postJson('/api/pedidos', [])
        ->assertUnprocessable()
        ->assertJsonPath('error.codigo', 'VALIDACION')
        ->assertJsonPath('error.campos.comercio_id.0', 'Elige el comercio del pedido.')
        ->assertJsonPath('error.campos.cliente_id.0', 'Elige el cliente del pedido.')
        ->assertJsonPath('error.campos.prioridad.0', 'El campo prioridad es obligatorio.')
        ->assertJsonPath('error.campos.franja_inicio.0', 'El campo inicio de la franja es obligatorio.')
        ->assertJsonPath('error.campos.franja_fin.0', 'El campo fin de la franja es obligatorio.')
        ->assertJsonPath('error.campos.lineas.0', 'Agrega al menos un producto.');

    expect(Pedido::query()->count())->toBe(0);
});

test('un pedido necesita al menos un producto', function () {
    Sanctum::actingAs($this->operador);

    $this->postJson('/api/pedidos', datosPedido(['lineas' => []]))
        ->assertUnprocessable()
        ->assertJsonPath('error.campos.lineas.0', 'Agrega al menos un producto.');

    expect(Pedido::query()->count())->toBe(0);
});

test('las cantidades deben ser enteros mayores que 0', function (mixed $cantidad, string $mensaje) {
    Sanctum::actingAs($this->operador);

    $respuesta = $this->postJson('/api/pedidos', datosPedido(['lineas' => [
        ['producto' => 'Pan de bono x12', 'cantidad' => 2],
        ['producto' => 'Torta de chocolate', 'cantidad' => $cantidad],
    ]]))
        ->assertUnprocessable()
        ->assertJsonPath('error.codigo', 'VALIDACION');

    // La clave del error es el campo tal cual, con puntos: "lineas.1.cantidad".
    expect($respuesta->json('error.campos'))->toBe(['lineas.1.cantidad' => [$mensaje]])
        ->and(Pedido::query()->count())->toBe(0)
        ->and(LineaPedido::query()->count())->toBe(0);
})->with([
    'cero' => [0, 'Debe ser mayor que 0.'],
    'negativa' => [-3, 'Debe ser mayor que 0.'],
    'vacía' => [null, 'Debe ser mayor que 0.'],
    'decimal' => [1.5, 'Debe ser un número entero.'],
]);

test('cada línea necesita el nombre del producto', function () {
    Sanctum::actingAs($this->operador);

    $respuesta = $this->postJson('/api/pedidos', datosPedido(['lineas' => [['producto' => '', 'cantidad' => 1]]]))
        ->assertUnprocessable();

    expect($respuesta->json('error.campos'))->toBe(['lineas.0.producto' => ['Escribe el producto.']]);
});

test('la franja no puede empezar en el pasado', function () {
    Sanctum::actingAs($this->operador);

    $this->postJson('/api/pedidos', datosPedido([
        'franja_inicio' => now()->subHour()->toIso8601String(),
        'franja_fin' => now()->addHour()->toIso8601String(),
    ]))
        ->assertUnprocessable()
        ->assertJsonPath('error.campos.franja_inicio.0', 'La franja no puede empezar en el pasado.');

    expect(Pedido::query()->count())->toBe(0);
});

test('la franja debe terminar después de empezar', function (int $minutos) {
    Sanctum::actingAs($this->operador);
    $inicio = now()->addDay();

    $this->postJson('/api/pedidos', datosPedido([
        'franja_inicio' => $inicio->toIso8601String(),
        'franja_fin' => $inicio->copy()->addMinutes($minutos)->toIso8601String(),
    ]))
        ->assertUnprocessable()
        ->assertJsonPath('error.campos.franja_fin.0', 'La franja debe terminar después de empezar.');

    expect(Pedido::query()->count())->toBe(0);
})->with(['termina antes' => -30, 'termina a la misma hora' => 0]);

test('la prioridad debe ser baja, media o alta', function () {
    Sanctum::actingAs($this->operador);

    $this->postJson('/api/pedidos', datosPedido(['prioridad' => 'urgente']))
        ->assertUnprocessable()
        ->assertJsonPath('error.campos.prioridad.0', 'El valor elegido para prioridad no es válido.');
});

test('la franja se guarda en UTC aunque llegue con otra zona horaria', function () {
    Sanctum::actingAs($this->operador);
    $inicio = now()->addDays(2)->setTime(15, 0);

    $id = $this->postJson('/api/pedidos', datosPedido([
        'franja_inicio' => $inicio->copy()->setTimezone('America/Bogota')->toIso8601String(),
        'franja_fin' => $inicio->copy()->addHour()->setTimezone('America/Bogota')->toIso8601String(),
    ]))->assertCreated()->json('data.id');

    expect(Pedido::query()->findOrFail($id)->franja_inicio->equalTo($inicio))->toBeTrue();
});

test('crear pedidos responde 401 sin token', function () {
    $this->postJson('/api/pedidos', [])
        ->assertUnauthorized()
        ->assertJsonPath('error.codigo', 'NO_AUTENTICADO');
});

test('el repartidor no puede crear pedidos', function () {
    Sanctum::actingAs(Usuario::factory()->repartidor()->create());

    $this->postJson('/api/pedidos', datosPedido())
        ->assertForbidden()
        ->assertJsonPath('error.codigo', 'NO_AUTORIZADO');

    expect(Pedido::query()->count())->toBe(0);
});
