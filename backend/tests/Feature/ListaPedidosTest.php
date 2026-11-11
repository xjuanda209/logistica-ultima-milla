<?php

use App\Modules\Administracion\Models\Comercio;
use App\Modules\Administracion\Models\Usuario;
use App\Modules\Administracion\Models\Zona;
use App\Modules\Incidencias\Models\Incidencia;
use App\Modules\Incidencias\Models\TipoIncidencia;
use App\Modules\Pedidos\Models\Pedido;
use App\Shared\Enums\EstadoIncidencia;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->operador = Usuario::factory()->operador()->create();
});

function incidenciaDe(Pedido $pedido, EstadoIncidencia $estado): Incidencia
{
    return Incidencia::query()->create([
        'pedido_id' => $pedido->id,
        'tipo_incidencia_id' => TipoIncidencia::factory()->create()->id,
        'usuario_id' => Usuario::factory()->repartidor()->create()->id,
        'descripcion' => 'Nadie atendió.',
        'estado' => $estado,
    ]);
}

test('la lista de pedidos viene paginada y con el más reciente primero', function () {
    Sanctum::actingAs($this->operador);
    $antiguo = Pedido::factory()->create(['created_at' => now()->subDays(3)]);
    $reciente = Pedido::factory()->create(['created_at' => now()]);
    $intermedio = Pedido::factory()->create(['created_at' => now()->subDay()]);

    $respuesta = $this->getJson('/api/pedidos')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [['id', 'numero', 'estado', 'created_at', 'incidencia_abierta', 'cliente' => ['nombre'], 'comercio' => ['nombre']]],
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ]);

    expect(collect($respuesta->json('data'))->pluck('id')->all())
        ->toBe([$reciente->id, $intermedio->id, $antiguo->id]);
});

test('la lista de pedidos trae 20 por página', function () {
    Sanctum::actingAs($this->operador);
    Pedido::factory()->count(23)->create();

    $this->getJson('/api/pedidos')->assertOk()->assertJsonCount(20, 'data')->assertJsonPath('meta.total', 23);
    $this->getJson('/api/pedidos?page=2')->assertOk()->assertJsonCount(3, 'data');
});

test('la lista de pedidos se filtra por zona', function () {
    Sanctum::actingAs($this->operador);
    $centro = Zona::factory()->create();
    $delCentro = Pedido::factory()->create(['zona_id' => $centro->id]);
    Pedido::factory()->count(2)->create();

    $this->getJson("/api/pedidos?zona_id={$centro->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $delCentro->id);
});

test('la lista de pedidos se filtra por rango de fechas de creación, con ambos extremos incluidos', function () {
    Sanctum::actingAs($this->operador);
    $dia = now()->startOfDay();
    Pedido::factory()->create(['created_at' => $dia->copy()->subSecond()]);
    $alInicio = Pedido::factory()->create(['created_at' => $dia->copy()]);
    $alFinal = Pedido::factory()->create(['created_at' => $dia->copy()->endOfDay()->startOfSecond()]);
    Pedido::factory()->create(['created_at' => $dia->copy()->addDay()]);

    $desde = urlencode($dia->toIso8601String());
    $hasta = urlencode($dia->copy()->endOfDay()->toIso8601String());

    $respuesta = $this->getJson("/api/pedidos?desde={$desde}&hasta={$hasta}")->assertOk()->assertJsonCount(2, 'data');

    expect(collect($respuesta->json('data'))->pluck('id')->sort()->values()->all())
        ->toBe([$alInicio->id, $alFinal->id]);

    $this->getJson("/api/pedidos?desde={$desde}")->assertOk()->assertJsonCount(3, 'data');
    $this->getJson("/api/pedidos?hasta={$hasta}")->assertOk()->assertJsonCount(3, 'data');
});

test('el rango de fechas respeta la zona horaria con la que llega', function () {
    Sanctum::actingAs($this->operador);
    // 11 de noviembre a las 23:30 en Bogotá = 12 de noviembre a las 04:30 UTC.
    $pedido = Pedido::factory()->create(['created_at' => '2026-11-12 04:30:00']);

    $dia11 = 'desde='.urlencode('2026-11-11T00:00:00-05:00').'&hasta='.urlencode('2026-11-11T23:59:59-05:00');
    $dia12 = 'desde='.urlencode('2026-11-12T00:00:00-05:00').'&hasta='.urlencode('2026-11-12T23:59:59-05:00');

    $this->getJson("/api/pedidos?{$dia11}")->assertOk()->assertJsonPath('data.0.id', $pedido->id);
    $this->getJson("/api/pedidos?{$dia12}")->assertOk()->assertJsonCount(0, 'data');
});

test('el rango de fechas debe ser válido', function () {
    Sanctum::actingAs($this->operador);

    $this->getJson('/api/pedidos?desde=ayer-no')
        ->assertUnprocessable()
        ->assertJsonPath('error.codigo', 'VALIDACION')
        ->assertJsonPath('error.campos.desde.0', 'El campo fecha desde no es una fecha válida.');

    $this->getJson('/api/pedidos?desde=2026-11-10&hasta=2026-11-01')
        ->assertUnprocessable()
        ->assertJsonPath('error.campos.hasta.0', 'La fecha hasta no puede ser anterior a la fecha desde.');
});

test('la lista marca los pedidos que tienen una incidencia abierta', function () {
    Sanctum::actingAs($this->operador);
    $conAbierta = Pedido::factory()->create();
    incidenciaDe($conAbierta, EstadoIncidencia::Resuelta);
    incidenciaDe($conAbierta, EstadoIncidencia::Abierta);
    $conResuelta = Pedido::factory()->create();
    incidenciaDe($conResuelta, EstadoIncidencia::Resuelta);
    incidenciaDe($conResuelta, EstadoIncidencia::EnRevision);
    $sinIncidencias = Pedido::factory()->create();

    $marcas = collect($this->getJson('/api/pedidos')->assertOk()->json('data'))->pluck('incidencia_abierta', 'id');

    expect($marcas[$conAbierta->id])->toBeTrue()
        ->and($marcas[$conResuelta->id])->toBeFalse()
        ->and($marcas[$sinIncidencias->id])->toBeFalse();
});

test('los filtros se pueden combinar', function () {
    Sanctum::actingAs($this->operador);
    $zona = Zona::factory()->create();
    $repartidor = Usuario::factory()->repartidor()->create();
    $buscado = Pedido::factory()->asignadoA($repartidor)->create(['zona_id' => $zona->id, 'numero' => 'PED-0007']);
    Pedido::factory()->create(['zona_id' => $zona->id, 'numero' => 'PED-0008']);
    Pedido::factory()->asignadoA($repartidor)->create(['numero' => 'PED-0009']);

    $this->getJson("/api/pedidos?estado=asignado&zona_id={$zona->id}&numero=ped-000")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $buscado->id);
});

test('un comercio no ve pedidos ajenos con ningún filtro', function () {
    $miComercio = Comercio::factory()->create();
    $zona = Zona::factory()->create();
    $propio = Pedido::factory()->create(['comercio_id' => $miComercio->id, 'zona_id' => $zona->id, 'numero' => 'PED-0001']);
    Pedido::factory()->create(['zona_id' => $zona->id, 'numero' => 'PED-0002']);

    Sanctum::actingAs(Usuario::factory()->comercio($miComercio)->create());

    $desde = urlencode(now()->subDay()->toIso8601String());

    foreach (["zona_id={$zona->id}", "desde={$desde}", 'estado=creado', 'numero=PED-000'] as $filtro) {
        $this->getJson("/api/pedidos?{$filtro}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $propio->id);
    }

    $this->getJson('/api/pedidos?numero=PED-0002')->assertOk()->assertJsonCount(0, 'data');
});

test('la lista de pedidos responde 401 sin token', function () {
    $this->getJson('/api/pedidos')
        ->assertUnauthorized()
        ->assertJsonPath('error.codigo', 'NO_AUTENTICADO');
});

test('los pedidos no se pueden eliminar', function () {
    Sanctum::actingAs(Usuario::factory()->administrador()->create());
    $pedido = Pedido::factory()->create();

    $this->deleteJson("/api/pedidos/{$pedido->id}")->assertStatus(405);

    expect(Pedido::query()->whereKey($pedido->id)->exists())->toBeTrue();
});
