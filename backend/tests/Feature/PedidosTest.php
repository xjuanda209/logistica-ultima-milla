<?php

use App\Modules\Administracion\Models\Comercio;
use App\Modules\Administracion\Models\Usuario;
use App\Modules\Incidencias\Models\Incidencia;
use App\Modules\Incidencias\Models\TipoIncidencia;
use App\Modules\Pedidos\Models\Cliente;
use App\Modules\Pedidos\Models\Pedido;
use App\Shared\Enums\EstadoPedido;
use Laravel\Sanctum\Sanctum;

test('un comercio solo ve sus propios pedidos en la lista', function () {
    $miComercio = Comercio::factory()->create();
    $otroComercio = Comercio::factory()->create();
    $propios = Pedido::factory()->count(2)->create(['comercio_id' => $miComercio->id]);
    Pedido::factory()->count(3)->create(['comercio_id' => $otroComercio->id]);

    Sanctum::actingAs(Usuario::factory()->comercio($miComercio)->create());

    $respuesta = $this->getJson('/api/pedidos')->assertOk()->assertJsonCount(2, 'data');

    expect(collect($respuesta->json('data'))->pluck('id')->sort()->values()->all())
        ->toBe($propios->pluck('id')->sort()->values()->all());
});

test('un comercio no puede ver el detalle de un pedido de otro comercio', function () {
    $miComercio = Comercio::factory()->create();
    $pedidoAjeno = Pedido::factory()->create();

    Sanctum::actingAs(Usuario::factory()->comercio($miComercio)->create());

    $this->getJson("/api/pedidos/{$pedidoAjeno->id}")
        ->assertNotFound()
        ->assertJsonPath('error.codigo', 'NO_ENCONTRADO');
});

test('un comercio ve el detalle de su pedido con líneas e historial', function () {
    $miComercio = Comercio::factory()->create();
    $pedido = Pedido::factory()->create(['comercio_id' => $miComercio->id]);
    $pedido->lineas()->create(['producto' => 'Café', 'cantidad' => 2]);

    Sanctum::actingAs(Usuario::factory()->comercio($miComercio)->create());

    $this->getJson("/api/pedidos/{$pedido->id}")
        ->assertOk()
        ->assertJsonPath('data.numero', $pedido->numero)
        ->assertJsonPath('data.lineas.0.producto', 'Café')
        ->assertJsonStructure(['data' => ['cliente', 'comercio', 'zona', 'historial']]);
});

test('un repartidor solo ve los pedidos que tiene asignados', function () {
    $repartidor = Usuario::factory()->repartidor()->create();
    $otroRepartidor = Usuario::factory()->repartidor()->create();
    $asignado = Pedido::factory()->asignadoA($repartidor)->create();
    $ajeno = Pedido::factory()->asignadoA($otroRepartidor)->create();
    Pedido::factory()->create();

    Sanctum::actingAs($repartidor);

    $this->getJson('/api/pedidos')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $asignado->id);

    $this->getJson("/api/pedidos/{$ajeno->id}")->assertNotFound();
});

test('administrador y operador ven los pedidos de todos los comercios', function (string $rol) {
    Pedido::factory()->count(3)->create();

    Sanctum::actingAs(Usuario::factory()->{$rol}()->create());

    $this->getJson('/api/pedidos')->assertOk()->assertJsonCount(3, 'data');
})->with(['administrador', 'operador']);

test('la lista de pedidos se puede filtrar por estado y rechaza estados inválidos', function () {
    $repartidor = Usuario::factory()->repartidor()->create();
    Pedido::factory()->count(2)->create();
    Pedido::factory()->asignadoA($repartidor)->create();

    Sanctum::actingAs(Usuario::factory()->operador()->create());

    $this->getJson('/api/pedidos?estado=asignado')->assertOk()->assertJsonCount(1, 'data');

    $this->getJson('/api/pedidos?estado=inventado')
        ->assertUnprocessable()
        ->assertJsonPath('error.codigo', 'VALIDACION');
});

test('un comercio solo ve sus propios clientes', function () {
    $miComercio = Comercio::factory()->create();
    Cliente::factory()->count(2)->create(['comercio_id' => $miComercio->id]);
    Cliente::factory()->count(3)->create();

    Sanctum::actingAs(Usuario::factory()->comercio($miComercio)->create());

    $this->getJson('/api/clientes')->assertOk()->assertJsonCount(2, 'data');
});

test('un comercio solo ve las incidencias de sus pedidos', function () {
    $miComercio = Comercio::factory()->create();
    $tipo = TipoIncidencia::query()->create(['nombre' => 'Cliente ausente']);
    $reporta = Usuario::factory()->repartidor()->create();

    foreach ([Pedido::factory()->create(['comercio_id' => $miComercio->id]), Pedido::factory()->create()] as $pedido) {
        Incidencia::query()->create([
            'pedido_id' => $pedido->id,
            'tipo_incidencia_id' => $tipo->id,
            'usuario_id' => $reporta->id,
            'descripcion' => 'Nadie atendió',
        ]);
    }

    Sanctum::actingAs(Usuario::factory()->comercio($miComercio)->create());

    $this->getJson('/api/incidencias')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.tipo.nombre', 'Cliente ausente');
});

test('el panel cuenta los pedidos por estado según lo que ve cada usuario', function () {
    $miComercio = Comercio::factory()->create();
    $repartidor = Usuario::factory()->repartidor()->create();
    Pedido::factory()->count(2)->create(['comercio_id' => $miComercio->id]);
    Pedido::factory()->asignadoA($repartidor)->create(['comercio_id' => $miComercio->id]);
    Pedido::factory()->count(4)->create();

    Sanctum::actingAs(Usuario::factory()->comercio($miComercio)->create());

    $respuesta = $this->getJson('/api/panel/resumen')
        ->assertOk()
        ->assertJsonPath('data.total', 3)
        ->assertJsonPath('data.por_estado.creado', 2)
        ->assertJsonPath('data.por_estado.asignado', 1)
        ->assertJsonPath('data.por_estado.entregado', 0);

    expect(array_keys($respuesta->json('data.por_estado')))
        ->toBe(array_column(EstadoPedido::cases(), 'value'));

    Sanctum::actingAs(Usuario::factory()->operador()->create());

    $this->getJson('/api/panel/resumen')->assertJsonPath('data.total', 7);
});
