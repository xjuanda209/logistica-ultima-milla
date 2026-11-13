<?php

use App\Modules\Administracion\Models\Comercio;
use App\Modules\Administracion\Models\Configuracion;
use App\Modules\Administracion\Models\Usuario;
use App\Modules\Administracion\Models\Zona;
use App\Modules\Pedidos\Models\HistorialPedido;
use App\Modules\Pedidos\Models\Pedido;
use App\Shared\Enums\Canal;
use App\Shared\Enums\Disponibilidad;
use App\Shared\Enums\EstadoPedido;
use App\Shared\Enums\TipoEvento;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Configuracion::query()->create(['id' => 1, 'max_pedidos_activos' => 3]);
    $this->zona = Zona::factory()->create();
    $this->pedido = Pedido::factory()->create(['zona_id' => $this->zona->id]);
    $this->repartidor = Usuario::factory()->repartidor($this->zona)->create(['nombre' => 'Rita Repartidora']);
    $this->operador = Usuario::factory()->operador()->create();
});

/**
 * Deja al repartidor con esa cantidad de pedidos en el estado dado.
 */
function darPedidos(Usuario $repartidor, int $cantidad, string $estado = 'asignado'): void
{
    Pedido::factory()->count($cantidad)->create(['repartidor_id' => $repartidor->id, 'estado' => $estado]);
}

test('administrador y operador asignan un pedido creado', function (string $rol) {
    $usuario = Usuario::factory()->{$rol}()->create();
    Sanctum::actingAs($usuario);

    $this->putJson("/api/pedidos/{$this->pedido->id}/repartidor", ['repartidor_id' => $this->repartidor->id])
        ->assertOk()
        ->assertJsonPath('data.estado', 'asignado')
        ->assertJsonPath('data.repartidor.id', $this->repartidor->id)
        ->assertJsonPath('data.historial.0.tipo_evento', 'asignacion');

    $evento = HistorialPedido::query()->where('pedido_id', $this->pedido->id)->sole();

    expect($this->pedido->fresh()->repartidor_id)->toBe($this->repartidor->id)
        ->and($evento->tipo_evento)->toBe(TipoEvento::Asignacion)
        ->and($evento->estado_anterior)->toBe(EstadoPedido::Creado)
        ->and($evento->estado_nuevo)->toBe(EstadoPedido::Asignado)
        ->and($evento->usuario_id)->toBe($usuario->id)
        ->and($evento->canal)->toBe(Canal::Web)
        ->and($evento->detalle)->toBe('Repartidor: Rita Repartidora');
})->with(['administrador', 'operador']);

test('el canal del historial sale del encabezado X-Canal', function () {
    Sanctum::actingAs($this->operador);

    $this->putJson("/api/pedidos/{$this->pedido->id}/repartidor", ['repartidor_id' => $this->repartidor->id], ['X-Canal' => 'movil'])
        ->assertOk();

    expect(HistorialPedido::query()->sole()->canal)->toBe(Canal::Movil);
});

test('un pedido asignado se reasigna a otro repartidor y el historial nombra a ambos', function () {
    Sanctum::actingAs($this->operador);
    $otro = Usuario::factory()->repartidor($this->zona)->create(['nombre' => 'Otto Otro']);
    $this->pedido->update(['repartidor_id' => $this->repartidor->id, 'estado' => EstadoPedido::Asignado]);

    $this->putJson("/api/pedidos/{$this->pedido->id}/repartidor", ['repartidor_id' => $otro->id])
        ->assertOk()
        ->assertJsonPath('data.estado', 'asignado')
        ->assertJsonPath('data.repartidor.id', $otro->id);

    $evento = HistorialPedido::query()->sole();

    expect($evento->estado_anterior)->toBe(EstadoPedido::Asignado)
        ->and($evento->estado_nuevo)->toBe(EstadoPedido::Asignado)
        ->and($evento->detalle)->toBe('Repartidor: Otto Otro (antes: Rita Repartidora)');
});

test('no se puede reasignar al mismo repartidor', function () {
    Sanctum::actingAs($this->operador);
    $this->pedido->update(['repartidor_id' => $this->repartidor->id, 'estado' => EstadoPedido::Asignado]);

    $this->putJson("/api/pedidos/{$this->pedido->id}/repartidor", ['repartidor_id' => $this->repartidor->id])
        ->assertStatus(409)
        ->assertJsonPath('error.codigo', 'MISMO_REPARTIDOR');

    expect(HistorialPedido::query()->count())->toBe(0);
});

test('se rechaza con el motivo si el repartidor no puede recibir el pedido', function (string $codigo, Closure $preparar) {
    Sanctum::actingAs($this->operador);
    $preparar($this->repartidor);

    $this->putJson("/api/pedidos/{$this->pedido->id}/repartidor", ['repartidor_id' => $this->repartidor->id])
        ->assertStatus(409)
        ->assertJsonPath('error.codigo', $codigo);

    $pedido = $this->pedido->fresh();

    expect($pedido->estado)->toBe(EstadoPedido::Creado)
        ->and($pedido->repartidor_id)->toBeNull()
        ->and(HistorialPedido::query()->count())->toBe(0);
})->with([
    'desactivado' => ['REPARTIDOR_DESACTIVADO', fn (Usuario $r) => $r->update(['activo' => false])],
    'no disponible' => ['REPARTIDOR_NO_DISPONIBLE', fn (Usuario $r) => $r->update(['disponibilidad' => Disponibilidad::NoDisponible])],
    'fuera de zona' => ['REPARTIDOR_FUERA_DE_ZONA', fn (Usuario $r) => $r->update(['zona_id' => Zona::factory()->create()->id])],
    'máximo alcanzado' => ['REPARTIDOR_AL_MAXIMO', fn (Usuario $r) => darPedidos($r, 3)],
]);

test('para el máximo cuentan los pedidos en asignado, recogido y en ruta', function () {
    Sanctum::actingAs($this->operador);
    darPedidos($this->repartidor, 1, 'asignado');
    darPedidos($this->repartidor, 1, 'recogido');
    darPedidos($this->repartidor, 1, 'en_ruta');

    $this->putJson("/api/pedidos/{$this->pedido->id}/repartidor", ['repartidor_id' => $this->repartidor->id])
        ->assertStatus(409)
        ->assertJsonPath('error.codigo', 'REPARTIDOR_AL_MAXIMO');
});

test('los pedidos entregados, no entregados y cancelados no cuentan para el máximo', function () {
    Sanctum::actingAs($this->operador);
    darPedidos($this->repartidor, 2, 'asignado');
    darPedidos($this->repartidor, 2, 'entregado');
    darPedidos($this->repartidor, 1, 'no_entregado');
    darPedidos($this->repartidor, 1, 'cancelado');

    $this->putJson("/api/pedidos/{$this->pedido->id}/repartidor", ['repartidor_id' => $this->repartidor->id])
        ->assertOk();
});

test('un pedido que ya salió no se puede asignar', function (string $estado) {
    Sanctum::actingAs($this->operador);
    $this->pedido->update(['estado' => $estado]);

    $this->putJson("/api/pedidos/{$this->pedido->id}/repartidor", ['repartidor_id' => $this->repartidor->id])
        ->assertStatus(409)
        ->assertJsonPath('error.codigo', 'TRANSICION_NO_PERMITIDA');

    expect($this->pedido->fresh()->repartidor_id)->toBeNull();
})->with(['recogido', 'en_ruta', 'entregado', 'no_entregado', 'cancelado']);

test('el repartidor es obligatorio y debe ser un usuario con rol repartidor', function (mixed $repartidorId) {
    Sanctum::actingAs($this->operador);

    $this->putJson("/api/pedidos/{$this->pedido->id}/repartidor", ['repartidor_id' => $repartidorId instanceof Closure ? $repartidorId() : $repartidorId])
        ->assertStatus(422)
        ->assertJsonPath('error.codigo', 'VALIDACION')
        ->assertJsonStructure(['error' => ['campos' => ['repartidor_id']]]);
})->with([
    'vacío' => [null],
    'inexistente' => [999999],
    'un operador' => [fn () => Usuario::factory()->operador()->create()->id],
]);

test('asignar un pedido que no existe responde 404', function () {
    Sanctum::actingAs($this->operador);

    $this->putJson('/api/pedidos/999999/repartidor', ['repartidor_id' => $this->repartidor->id])
        ->assertNotFound()
        ->assertJsonPath('error.codigo', 'NO_ENCONTRADO');
});

test('la lista de candidatos pone primero a los elegibles, de menor a mayor carga, y da el motivo de los demás', function () {
    Sanctum::actingAs($this->operador);
    darPedidos($this->repartidor, 1);
    $libre = Usuario::factory()->repartidor($this->zona)->create(['nombre' => 'Ana Libre']);
    $noDisponible = Usuario::factory()->repartidor($this->zona)->create(['nombre' => 'Beto', 'disponibilidad' => Disponibilidad::NoDisponible]);
    $otraZona = Usuario::factory()->repartidor()->create(['nombre' => 'Caro']);
    $lleno = Usuario::factory()->repartidor($this->zona)->create(['nombre' => 'Dani']);
    darPedidos($lleno, 3);
    Usuario::factory()->repartidor($this->zona)->inactivo()->create();

    $respuesta = $this->getJson("/api/pedidos/{$this->pedido->id}/repartidores")
        ->assertOk()
        ->assertJsonCount(5, 'data')
        ->assertJsonStructure(['data' => [['id', 'nombre', 'correo', 'activo', 'disponibilidad', 'zona' => ['id', 'nombre'], 'pedidos_activos', 'max_pedidos_activos', 'elegible', 'motivo']]])
        ->assertJsonPath('data.0.id', $libre->id)
        ->assertJsonPath('data.0.elegible', true)
        ->assertJsonPath('data.0.motivo', null)
        ->assertJsonPath('data.0.pedidos_activos', 0)
        ->assertJsonPath('data.0.max_pedidos_activos', 3)
        ->assertJsonPath('data.1.id', $this->repartidor->id)
        ->assertJsonPath('data.1.elegible', true)
        ->assertJsonPath('data.1.pedidos_activos', 1);

    $motivos = collect($respuesta->json('data'))->where('elegible', false)->pluck('motivo', 'id');

    expect($motivos->all())->toBe([
        $noDisponible->id => 'No disponible',
        $otraZona->id => 'Fuera de la zona del pedido',
        $lleno->id => 'Alcanzó el máximo de pedidos activos',
    ]);
});

test('al reasignar, el repartidor actual aparece como no elegible', function () {
    Sanctum::actingAs($this->operador);
    $this->pedido->update(['repartidor_id' => $this->repartidor->id, 'estado' => EstadoPedido::Asignado]);

    $this->getJson("/api/pedidos/{$this->pedido->id}/repartidores")
        ->assertOk()
        ->assertJsonPath('data.0.elegible', false)
        ->assertJsonPath('data.0.motivo', 'Ya tiene asignado este pedido');
});

dataset('rutas de asignación', [
    'candidatos' => ['getJson', 'repartidores'],
    'asignar' => ['putJson', 'repartidor'],
]);

test('asignar exige autenticación', function (string $metodo, string $ruta) {
    $this->{$metodo}("/api/pedidos/{$this->pedido->id}/{$ruta}")
        ->assertUnauthorized()
        ->assertJsonPath('error.codigo', 'NO_AUTENTICADO');
})->with('rutas de asignación');

test('comercio y repartidor no pueden asignar ni ver candidatos', function (string $rol, string $metodo, string $ruta) {
    Sanctum::actingAs(Usuario::factory()->{$rol}($rol === 'comercio' ? Comercio::query()->find($this->pedido->comercio_id) : null)->create());

    $this->{$metodo}("/api/pedidos/{$this->pedido->id}/{$ruta}", $metodo === 'putJson' ? ['repartidor_id' => $this->repartidor->id] : [])
        ->assertForbidden()
        ->assertJsonPath('error.codigo', 'NO_AUTORIZADO');

    expect($this->pedido->fresh()->estado)->toBe(EstadoPedido::Creado);
})->with(['comercio', 'repartidor'])->with('rutas de asignación');
