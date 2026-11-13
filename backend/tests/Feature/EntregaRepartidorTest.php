<?php

use App\Modules\Administracion\Models\Usuario;
use App\Modules\Incidencias\Models\TipoIncidencia;
use App\Modules\Pedidos\Models\HistorialPedido;
use App\Modules\Pedidos\Models\Pedido;
use App\Shared\Enums\Canal;
use App\Shared\Enums\EstadoPedido;
use App\Shared\Enums\TipoEvento;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->repartidor = Usuario::factory()->repartidor()->create();
    $this->pedido = Pedido::factory()->asignadoA($this->repartidor)->create();
    Sanctum::actingAs($this->repartidor);
});

function ponerEvidencia(Pedido $pedido): void
{
    $pedido->evidencias()->create([
        'usuario_id' => $pedido->repartidor_id,
        'tipo' => 'foto_entrega',
        'ruta_archivo' => 'evidencias/foto.jpg',
        'created_at' => now(),
    ]);
}

function ponerIncidencia(Pedido $pedido): void
{
    $pedido->incidencias()->create([
        'tipo_incidencia_id' => TipoIncidencia::factory()->create()->id,
        'usuario_id' => $pedido->repartidor_id,
        'descripcion' => 'El cliente no atendió.',
    ]);
}

test('el repartidor avanza su pedido: recogido y luego en ruta, con el canal en el historial', function () {
    $this->putJson("/api/pedidos/{$this->pedido->id}/estado", ['estado' => 'recogido'], ['X-Canal' => 'movil'])
        ->assertOk()
        ->assertJsonPath('data.estado', 'recogido');

    $this->putJson("/api/pedidos/{$this->pedido->id}/estado", ['estado' => 'en_ruta'], ['X-Canal' => 'movil'])
        ->assertOk()
        ->assertJsonPath('data.estado', 'en_ruta');

    $eventos = HistorialPedido::query()->where('pedido_id', $this->pedido->id)->orderBy('id')->get();

    expect($eventos)->toHaveCount(2)
        ->and($eventos[0]->tipo_evento)->toBe(TipoEvento::CambioEstado)
        ->and($eventos[0]->estado_anterior)->toBe(EstadoPedido::Asignado)
        ->and($eventos[0]->estado_nuevo)->toBe(EstadoPedido::Recogido)
        ->and($eventos[0]->canal)->toBe(Canal::Movil)
        ->and($eventos[0]->usuario_id)->toBe($this->repartidor->id)
        ->and($eventos[1]->estado_nuevo)->toBe(EstadoPedido::EnRuta);
});

test('una transición que no toca se rechaza y el pedido no cambia', function (string $actual, string $nuevo) {
    $this->pedido->update(['estado' => $actual]);

    $this->putJson("/api/pedidos/{$this->pedido->id}/estado", ['estado' => $nuevo, 'motivo' => 'x'])
        ->assertStatus(409)
        ->assertJsonPath('error.codigo', 'TRANSICION_NO_PERMITIDA');

    expect($this->pedido->fresh()->estado->value)->toBe($actual)
        ->and(HistorialPedido::query()->count())->toBe(0);
})->with([
    ['asignado', 'en_ruta'],
    ['asignado', 'entregado'],
    ['recogido', 'entregado'],
    ['recogido', 'no_entregado'],
    ['en_ruta', 'recogido'],
    ['entregado', 'no_entregado'],
    ['cancelado', 'recogido'],
]);

test('el repartidor no puede asignar ni cancelar por esta ruta', function (string $estado) {
    $this->putJson("/api/pedidos/{$this->pedido->id}/estado", ['estado' => $estado])
        ->assertStatus(422)
        ->assertJsonPath('error.codigo', 'VALIDACION')
        ->assertJsonPath('error.campos.estado.0', 'El valor elegido para estado no es válido.');

    expect($this->pedido->fresh()->estado)->toBe(EstadoPedido::Asignado);
})->with(['asignado', 'cancelado', 'creado', 'volando']);

test('entregado exige al menos una evidencia', function () {
    $this->pedido->update(['estado' => 'en_ruta']);

    $this->putJson("/api/pedidos/{$this->pedido->id}/estado", ['estado' => 'entregado'])
        ->assertStatus(409)
        ->assertJsonPath('error.codigo', 'FALTA_EVIDENCIA')
        ->assertJsonPath('error.mensaje', 'Falta la evidencia: registra al menos una antes de marcarlo como entregado.');

    expect($this->pedido->fresh()->estado)->toBe(EstadoPedido::EnRuta);

    ponerEvidencia($this->pedido);

    $this->putJson("/api/pedidos/{$this->pedido->id}/estado", ['estado' => 'entregado'])
        ->assertOk()
        ->assertJsonPath('data.estado', 'entregado');
});

test('no entregado exige evidencia, incidencia y motivo', function () {
    $this->pedido->update(['estado' => 'en_ruta']);
    $ruta = "/api/pedidos/{$this->pedido->id}/estado";

    $this->putJson($ruta, ['estado' => 'no_entregado'])
        ->assertStatus(422)
        ->assertJsonPath('error.campos.motivo.0', 'Escribe el motivo del no entregado.');

    $this->putJson($ruta, ['estado' => 'no_entregado', 'motivo' => '   '])
        ->assertStatus(422)
        ->assertJsonPath('error.campos.motivo.0', 'Escribe el motivo del no entregado.');

    $this->putJson($ruta, ['estado' => 'no_entregado', 'motivo' => 'Cliente ausente.'])
        ->assertStatus(409)
        ->assertJsonPath('error.codigo', 'FALTA_EVIDENCIA');

    ponerEvidencia($this->pedido);

    $this->putJson($ruta, ['estado' => 'no_entregado', 'motivo' => 'Cliente ausente.'])
        ->assertStatus(409)
        ->assertJsonPath('error.codigo', 'FALTA_INCIDENCIA')
        ->assertJsonPath('error.mensaje', 'Falta la incidencia: regístrala para marcar el pedido como no entregado.');

    expect($this->pedido->fresh()->estado)->toBe(EstadoPedido::EnRuta);

    ponerIncidencia($this->pedido);

    $this->putJson($ruta, ['estado' => 'no_entregado', 'motivo' => 'Cliente ausente.'])
        ->assertOk()
        ->assertJsonPath('data.estado', 'no_entregado');

    expect(HistorialPedido::query()->where('pedido_id', $this->pedido->id)->sole()->detalle)
        ->toBe('Motivo: Cliente ausente.');
});

test('el pedido de otro repartidor responde 404 y no cambia', function () {
    $ajeno = Pedido::factory()->asignadoA(Usuario::factory()->repartidor()->create())->create();

    $this->putJson("/api/pedidos/{$ajeno->id}/estado", ['estado' => 'recogido'])
        ->assertNotFound()
        ->assertJsonPath('error.codigo', 'NO_ENCONTRADO');

    expect($ajeno->fresh()->estado)->toBe(EstadoPedido::Asignado);
});

test('cambiar el estado exige autenticación', function () {
    $this->app['auth']->forgetGuards();

    $this->putJson("/api/pedidos/{$this->pedido->id}/estado", ['estado' => 'recogido'])
        ->assertUnauthorized()
        ->assertJsonPath('error.codigo', 'NO_AUTENTICADO');
});

test('solo el repartidor cambia el estado desde la app', function (string $rol) {
    Sanctum::actingAs(Usuario::factory()->{$rol}()->create());

    $this->putJson("/api/pedidos/{$this->pedido->id}/estado", ['estado' => 'recogido'])
        ->assertForbidden()
        ->assertJsonPath('error.codigo', 'NO_AUTORIZADO');

    expect($this->pedido->fresh()->estado)->toBe(EstadoPedido::Asignado);
})->with(['administrador', 'operador', 'comercio']);

test('mis pedidos: solo los activos del repartidor, por inicio de franja', function () {
    $this->pedido->update(['franja_inicio' => now()->addHours(5), 'franja_fin' => now()->addHours(6)]);
    $primero = Pedido::factory()->asignadoA($this->repartidor)->create([
        'estado' => 'en_ruta', 'franja_inicio' => now()->addHour(), 'franja_fin' => now()->addHours(2),
    ]);
    Pedido::factory()->asignadoA($this->repartidor)->create(['estado' => 'entregado']);
    Pedido::factory()->asignadoA($this->repartidor)->create(['estado' => 'cancelado']);
    Pedido::factory()->asignadoA(Usuario::factory()->repartidor()->create())->create();

    $this->getJson('/api/pedidos?activos=1')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $primero->id)
        ->assertJsonPath('data.1.id', $this->pedido->id);
});

test('el login y me traen la zona del repartidor', function () {
    $this->getJson('/api/me')
        ->assertOk()
        ->assertJsonPath('data.zona.id', $this->repartidor->zona_id);
});
