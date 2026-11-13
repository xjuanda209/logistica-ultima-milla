<?php

use App\Modules\Administracion\Models\Comercio;
use App\Modules\Administracion\Models\Usuario;
use App\Modules\Pedidos\Models\HistorialPedido;
use App\Modules\Pedidos\Models\Pedido;
use App\Shared\Enums\EstadoPedido;
use App\Shared\Enums\TipoEvento;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->pedido = Pedido::factory()->create();
    $this->operador = Usuario::factory()->operador()->create();
});

test('administrador y operador cancelan un pedido creado y el motivo queda en el historial', function (string $rol) {
    $usuario = Usuario::factory()->{$rol}()->create();
    Sanctum::actingAs($usuario);

    $this->putJson("/api/pedidos/{$this->pedido->id}/cancelacion", ['motivo' => 'El cliente ya no lo necesita.'])
        ->assertOk()
        ->assertJsonPath('data.estado', 'cancelado')
        ->assertJsonPath('data.historial.0.detalle', 'Motivo: El cliente ya no lo necesita.');

    $evento = HistorialPedido::query()->where('pedido_id', $this->pedido->id)->sole();

    expect($this->pedido->fresh()->estado)->toBe(EstadoPedido::Cancelado)
        ->and($evento->tipo_evento)->toBe(TipoEvento::CambioEstado)
        ->and($evento->estado_anterior)->toBe(EstadoPedido::Creado)
        ->and($evento->estado_nuevo)->toBe(EstadoPedido::Cancelado)
        ->and($evento->usuario_id)->toBe($usuario->id);
})->with(['administrador', 'operador']);

test('un pedido asignado se cancela y deja de contarle al repartidor', function () {
    Sanctum::actingAs($this->operador);
    $repartidor = Usuario::factory()->repartidor()->create();
    $this->pedido->update(['repartidor_id' => $repartidor->id, 'estado' => EstadoPedido::Asignado]);

    $this->putJson("/api/pedidos/{$this->pedido->id}/cancelacion", ['motivo' => 'Dirección equivocada.'])
        ->assertOk()
        ->assertJsonPath('data.estado', 'cancelado');

    $this->getJson('/api/repartidores')->assertJsonPath('data.0.pedidos_activos', 0);
});

test('solo se cancelan pedidos en creado o asignado', function (string $estado, string $etiqueta) {
    Sanctum::actingAs($this->operador);
    $this->pedido->update(['estado' => $estado]);

    $this->putJson("/api/pedidos/{$this->pedido->id}/cancelacion", ['motivo' => 'Ya no va.'])
        ->assertStatus(409)
        ->assertJsonPath('error.codigo', 'TRANSICION_NO_PERMITIDA')
        ->assertJsonPath('error.mensaje', "No se puede cancelar: el pedido está «{$etiqueta}».");

    expect($this->pedido->fresh()->estado->value)->toBe($estado)
        ->and(HistorialPedido::query()->count())->toBe(0);
})->with([
    ['recogido', 'Recogido'],
    ['en_ruta', 'En ruta'],
    ['entregado', 'Entregado'],
    ['no_entregado', 'No entregado'],
    ['cancelado', 'Cancelado'],
]);

test('el motivo de la cancelación es obligatorio', function (?string $motivo) {
    Sanctum::actingAs($this->operador);

    $this->putJson("/api/pedidos/{$this->pedido->id}/cancelacion", ['motivo' => $motivo])
        ->assertStatus(422)
        ->assertJsonPath('error.codigo', 'VALIDACION')
        ->assertJsonPath('error.campos.motivo.0', 'Escribe el motivo de la cancelación.');

    expect($this->pedido->fresh()->estado)->toBe(EstadoPedido::Creado);
})->with(['nulo' => [null], 'vacío' => [''], 'solo espacios' => ['   ']]);

test('cancelar un pedido que no existe responde 404', function () {
    Sanctum::actingAs($this->operador);

    $this->putJson('/api/pedidos/999999/cancelacion', ['motivo' => 'Ya no va.'])
        ->assertNotFound()
        ->assertJsonPath('error.codigo', 'NO_ENCONTRADO');
});

test('cancelar exige autenticación', function () {
    $this->putJson("/api/pedidos/{$this->pedido->id}/cancelacion", ['motivo' => 'Ya no va.'])
        ->assertUnauthorized()
        ->assertJsonPath('error.codigo', 'NO_AUTENTICADO');
});

test('comercio y repartidor no pueden cancelar', function (string $rol) {
    Sanctum::actingAs(Usuario::factory()->{$rol}($rol === 'comercio' ? Comercio::query()->find($this->pedido->comercio_id) : null)->create());

    $this->putJson("/api/pedidos/{$this->pedido->id}/cancelacion", ['motivo' => 'Ya no va.'])
        ->assertForbidden()
        ->assertJsonPath('error.codigo', 'NO_AUTORIZADO');

    expect($this->pedido->fresh()->estado)->toBe(EstadoPedido::Creado);
})->with(['comercio', 'repartidor']);
