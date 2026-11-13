<?php

use App\Modules\Administracion\Models\Configuracion;
use App\Modules\Administracion\Models\Usuario;
use App\Modules\Administracion\Models\Zona;
use App\Modules\Pedidos\Models\Pedido;
use App\Shared\Enums\Disponibilidad;
use App\Shared\Enums\EstadoPedido;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Configuracion::query()->create(['id' => 1, 'max_pedidos_activos' => 4]);
    $this->zona = Zona::factory()->create(['nombre' => 'Centro']);
    $this->repartidor = Usuario::factory()->repartidor($this->zona)->create(['nombre' => 'Beto']);
    $this->operador = Usuario::factory()->operador()->create();
});

test('lista solo repartidores, por nombre, con zona, disponibilidad y carga', function (string $rol) {
    Sanctum::actingAs(Usuario::factory()->{$rol}()->create());
    $ana = Usuario::factory()->repartidor()->inactivo()->create(['nombre' => 'Ana', 'disponibilidad' => Disponibilidad::NoDisponible]);
    Pedido::factory()->count(2)->asignadoA($this->repartidor)->create();
    Pedido::factory()->create(['repartidor_id' => $this->repartidor->id, 'estado' => EstadoPedido::EnRuta]);
    Pedido::factory()->create(['repartidor_id' => $this->repartidor->id, 'estado' => EstadoPedido::Entregado]);

    $this->getJson('/api/repartidores')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonStructure([
            'data' => [['id', 'nombre', 'correo', 'activo', 'disponibilidad', 'zona_id', 'zona' => ['id', 'nombre'], 'pedidos_activos', 'max_pedidos_activos']],
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ])
        ->assertJsonMissingPath('data.0.elegible')
        ->assertJsonPath('data.0.id', $ana->id)
        ->assertJsonPath('data.0.activo', false)
        ->assertJsonPath('data.0.disponibilidad', 'no_disponible')
        ->assertJsonPath('data.0.pedidos_activos', 0)
        ->assertJsonPath('data.1.id', $this->repartidor->id)
        ->assertJsonPath('data.1.correo', $this->repartidor->correo)
        ->assertJsonPath('data.1.zona.nombre', 'Centro')
        ->assertJsonPath('data.1.disponibilidad', 'disponible')
        ->assertJsonPath('data.1.pedidos_activos', 3)
        ->assertJsonPath('data.1.max_pedidos_activos', 4);
})->with(['administrador', 'operador']);

test('administrador y operador cambian la disponibilidad de cualquier repartidor', function (string $rol) {
    Sanctum::actingAs(Usuario::factory()->{$rol}()->create());

    $this->putJson("/api/repartidores/{$this->repartidor->id}/disponibilidad", ['disponibilidad' => 'no_disponible'])
        ->assertOk()
        ->assertJsonPath('data.id', $this->repartidor->id)
        ->assertJsonPath('data.disponibilidad', 'no_disponible')
        ->assertJsonPath('data.max_pedidos_activos', 4);

    expect($this->repartidor->fresh()->disponibilidad)->toBe(Disponibilidad::NoDisponible);
})->with(['administrador', 'operador']);

test('el repartidor cambia su propia disponibilidad', function () {
    Sanctum::actingAs($this->repartidor);

    $this->putJson("/api/repartidores/{$this->repartidor->id}/disponibilidad", ['disponibilidad' => 'no_disponible'])
        ->assertOk()
        ->assertJsonPath('data.disponibilidad', 'no_disponible');

    $this->putJson("/api/repartidores/{$this->repartidor->id}/disponibilidad", ['disponibilidad' => 'disponible'])
        ->assertOk();

    expect($this->repartidor->fresh()->disponibilidad)->toBe(Disponibilidad::Disponible);
});

test('el repartidor no puede cambiar la disponibilidad de otro: responde 404', function () {
    Sanctum::actingAs($this->repartidor);
    $otro = Usuario::factory()->repartidor()->create();

    $this->putJson("/api/repartidores/{$otro->id}/disponibilidad", ['disponibilidad' => 'no_disponible'])
        ->assertNotFound()
        ->assertJsonPath('error.codigo', 'NO_ENCONTRADO');

    expect($otro->fresh()->disponibilidad)->toBe(Disponibilidad::Disponible);
});

test('pasar a no disponible no le quita los pedidos que ya tiene, pero no recibe nuevos', function () {
    Sanctum::actingAs($this->operador);
    $suyo = Pedido::factory()->asignadoA($this->repartidor)->create();
    $nuevo = Pedido::factory()->create(['zona_id' => $this->zona->id]);

    $this->putJson("/api/repartidores/{$this->repartidor->id}/disponibilidad", ['disponibilidad' => 'no_disponible'])
        ->assertOk()
        ->assertJsonPath('data.pedidos_activos', 1);

    $suyo->refresh();

    expect($suyo->repartidor_id)->toBe($this->repartidor->id)
        ->and($suyo->estado)->toBe(EstadoPedido::Asignado);

    $this->putJson("/api/pedidos/{$nuevo->id}/repartidor", ['repartidor_id' => $this->repartidor->id])
        ->assertStatus(409)
        ->assertJsonPath('error.codigo', 'REPARTIDOR_NO_DISPONIBLE');
});

test('la disponibilidad es obligatoria y debe ser un valor conocido', function (mixed $valor) {
    Sanctum::actingAs($this->operador);

    $this->putJson("/api/repartidores/{$this->repartidor->id}/disponibilidad", ['disponibilidad' => $valor])
        ->assertStatus(422)
        ->assertJsonPath('error.codigo', 'VALIDACION')
        ->assertJsonStructure(['error' => ['campos' => ['disponibilidad']]]);

    expect($this->repartidor->fresh()->disponibilidad)->toBe(Disponibilidad::Disponible);
})->with(['nulo' => [null], 'desconocido' => ['ocupado']]);

test('cambiar la disponibilidad de un usuario que no es repartidor responde 404', function () {
    Sanctum::actingAs($this->operador);

    $this->putJson("/api/repartidores/{$this->operador->id}/disponibilidad", ['disponibilidad' => 'disponible'])
        ->assertNotFound()
        ->assertJsonPath('error.codigo', 'NO_ENCONTRADO');

    expect($this->operador->fresh()->disponibilidad)->toBeNull();
});

test('las rutas de repartidores exigen autenticación', function (string $metodo, string $ruta) {
    $this->{$metodo}(str_replace('{id}', (string) $this->repartidor->id, $ruta))
        ->assertUnauthorized()
        ->assertJsonPath('error.codigo', 'NO_AUTENTICADO');
})->with([
    'listar' => ['getJson', '/api/repartidores'],
    'disponibilidad' => ['putJson', '/api/repartidores/{id}/disponibilidad'],
]);

test('comercio y repartidor no pueden listar repartidores', function (string $rol) {
    Sanctum::actingAs(Usuario::factory()->{$rol}()->create());

    $this->getJson('/api/repartidores')
        ->assertForbidden()
        ->assertJsonPath('error.codigo', 'NO_AUTORIZADO');
})->with(['comercio', 'repartidor']);

test('el comercio no puede cambiar disponibilidades', function () {
    Sanctum::actingAs(Usuario::factory()->comercio()->create());

    $this->putJson("/api/repartidores/{$this->repartidor->id}/disponibilidad", ['disponibilidad' => 'no_disponible'])
        ->assertForbidden()
        ->assertJsonPath('error.codigo', 'NO_AUTORIZADO');

    expect($this->repartidor->fresh()->disponibilidad)->toBe(Disponibilidad::Disponible);
});
