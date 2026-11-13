<?php

use App\Modules\Administracion\Models\Comercio;
use App\Modules\Administracion\Models\Usuario;
use App\Modules\Administracion\Models\Zona;
use App\Modules\Incidencias\Models\Incidencia;
use App\Modules\Incidencias\Models\TipoIncidencia;
use App\Modules\Pedidos\Models\Pedido;
use App\Shared\Enums\Canal;
use App\Shared\Enums\EstadoPedido;
use App\Shared\Enums\TipoEvento;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->operador = Usuario::factory()->operador()->create();
});

/**
 * Pedido entregado $minutos después de crearse (la hora de entrega queda en el historial).
 *
 * @param  array<string, mixed>  $atributos
 */
function pedidoEntregadoEn(int $minutos, array $atributos = []): Pedido
{
    $creado = Carbon::parse('2026-11-05 10:00:00');
    $pedido = Pedido::factory()->create([...$atributos, 'estado' => EstadoPedido::Entregado, 'created_at' => $creado]);

    $pedido->historial()->create([
        'usuario_id' => $pedido->creado_por,
        'tipo_evento' => TipoEvento::CambioEstado,
        'estado_anterior' => EstadoPedido::EnRuta,
        'estado_nuevo' => EstadoPedido::Entregado,
        'canal' => Canal::Movil,
        'created_at' => $creado->copy()->addMinutes($minutos),
    ]);

    return $pedido;
}

function incidenciaDelPanel(Pedido $pedido, TipoIncidencia $tipo): Incidencia
{
    return Incidencia::query()->create([
        'pedido_id' => $pedido->id,
        'tipo_incidencia_id' => $tipo->id,
        'usuario_id' => $pedido->creado_por,
        'descripcion' => 'Nadie atendió.',
    ]);
}

test('el panel cuenta los pedidos por estado y calcula el porcentaje de entregas exitosas', function (string $rol) {
    Sanctum::actingAs(Usuario::factory()->{$rol}()->create());
    Pedido::factory()->count(2)->create();
    Pedido::factory()->count(3)->create(['estado' => EstadoPedido::Entregado]);
    Pedido::factory()->create(['estado' => EstadoPedido::NoEntregado]);
    Pedido::factory()->create(['estado' => EstadoPedido::Cancelado]);

    $respuesta = $this->getJson('/api/panel/indicadores')
        ->assertOk()
        ->assertJsonStructure(['data' => [
            'total', 'por_estado', 'porcentaje_exito', 'tiempo_promedio_minutos',
            'incidencias' => ['por_tipo', 'por_zona', 'por_repartidor'],
        ]])
        ->assertJsonPath('data.total', 7)
        ->assertJsonPath('data.por_estado.creado', 2)
        ->assertJsonPath('data.por_estado.entregado', 3)
        ->assertJsonPath('data.por_estado.no_entregado', 1)
        ->assertJsonPath('data.por_estado.cancelado', 1)
        ->assertJsonPath('data.por_estado.en_ruta', 0)
        ->assertJsonPath('data.porcentaje_exito', 75);

    expect(array_keys($respuesta->json('data.por_estado')))->toBe(array_column(EstadoPedido::cases(), 'value'));
})->with(['administrador', 'operador']);

test('el porcentaje de entregas exitosas se redondea a un decimal', function () {
    Sanctum::actingAs($this->operador);
    Pedido::factory()->count(2)->create(['estado' => EstadoPedido::Entregado]);
    Pedido::factory()->create(['estado' => EstadoPedido::NoEntregado]);

    $this->getJson('/api/panel/indicadores')->assertOk()->assertJsonPath('data.porcentaje_exito', 66.7);
});

test('sin base de cálculo los indicadores vienen vacíos (Sin datos)', function () {
    Sanctum::actingAs($this->operador);
    // Hay pedidos, pero ninguno terminó en entregado o no entregado.
    Pedido::factory()->create();
    Pedido::factory()->create(['estado' => EstadoPedido::Cancelado]);

    $this->getJson('/api/panel/indicadores')
        ->assertOk()
        ->assertJsonPath('data.total', 2)
        ->assertJsonPath('data.porcentaje_exito', null)
        ->assertJsonPath('data.tiempo_promedio_minutos', null)
        ->assertJsonPath('data.incidencias.por_tipo', [])
        ->assertJsonPath('data.incidencias.por_zona', [])
        ->assertJsonPath('data.incidencias.por_repartidor', []);
});

test('el tiempo promedio va de la creación a la entrega que quedó en el historial', function () {
    Sanctum::actingAs($this->operador);
    pedidoEntregadoEn(30);
    $otro = pedidoEntregadoEn(61);
    // Los eventos anteriores a la entrega y los pedidos sin entregar no cuentan.
    $otro->historial()->create([
        'usuario_id' => $otro->creado_por,
        'tipo_evento' => TipoEvento::Asignacion,
        'estado_anterior' => EstadoPedido::Creado,
        'estado_nuevo' => EstadoPedido::Asignado,
        'canal' => Canal::Web,
        'created_at' => Carbon::parse('2026-11-05 10:05:00'),
    ]);
    Pedido::factory()->create(['estado' => EstadoPedido::EnRuta]);
    // Tampoco una entrega con hora anterior a la creación (datos sembrados a mano).
    pedidoEntregadoEn(-500);

    // (30 + 61) ÷ 2 = 45,5 → 46 minutos.
    $this->getJson('/api/panel/indicadores')->assertOk()->assertJsonPath('data.tiempo_promedio_minutos', 46);
});

test('las incidencias se agrupan por tipo, zona y repartidor, de mayor a menor', function () {
    Sanctum::actingAs($this->operador);
    $centro = Zona::factory()->create(['nombre' => 'Centro']);
    $norte = Zona::factory()->create(['nombre' => 'Norte']);
    $beto = Usuario::factory()->repartidor($centro)->create(['nombre' => 'Beto']);
    $ana = Usuario::factory()->repartidor($norte)->create(['nombre' => 'Ana']);
    $ausente = TipoIncidencia::factory()->create(['nombre' => 'Cliente ausente']);
    $danado = TipoIncidencia::factory()->create(['nombre' => 'Paquete dañado']);

    $delCentro = Pedido::factory()->create(['zona_id' => $centro->id, 'repartidor_id' => $beto->id, 'estado' => EstadoPedido::NoEntregado]);
    $otroDelCentro = Pedido::factory()->create(['zona_id' => $centro->id, 'repartidor_id' => $beto->id, 'estado' => EstadoPedido::EnRuta]);
    $delNorte = Pedido::factory()->create(['zona_id' => $norte->id, 'repartidor_id' => $ana->id, 'estado' => EstadoPedido::NoEntregado]);
    $sinRepartidor = Pedido::factory()->create(['zona_id' => $norte->id]);

    incidenciaDelPanel($delCentro, $ausente);
    incidenciaDelPanel($delCentro, $danado);
    incidenciaDelPanel($otroDelCentro, $ausente);
    incidenciaDelPanel($delNorte, $ausente);
    incidenciaDelPanel($sinRepartidor, $danado);

    $this->getJson('/api/panel/indicadores')
        ->assertOk()
        ->assertJsonPath('data.incidencias.por_tipo', [
            ['etiqueta' => 'Cliente ausente', 'valor' => 3],
            ['etiqueta' => 'Paquete dañado', 'valor' => 2],
        ])
        ->assertJsonPath('data.incidencias.por_zona', [
            ['etiqueta' => 'Centro', 'valor' => 3],
            ['etiqueta' => 'Norte', 'valor' => 2],
        ])
        ->assertJsonPath('data.incidencias.por_repartidor', [
            ['etiqueta' => 'Beto', 'valor' => 3],
            ['etiqueta' => 'Ana', 'valor' => 1],
        ]);
});

test('los filtros de zona, comercio y estado aplican a todos los indicadores', function () {
    Sanctum::actingAs($this->operador);
    $zona = Zona::factory()->create(['nombre' => 'Centro']);
    $comercio = Comercio::factory()->create();
    $tipo = TipoIncidencia::factory()->create(['nombre' => 'Cliente ausente']);

    pedidoEntregadoEn(40, ['zona_id' => $zona->id, 'comercio_id' => $comercio->id]);
    $noEntregado = Pedido::factory()->create(['zona_id' => $zona->id, 'estado' => EstadoPedido::NoEntregado]);
    incidenciaDelPanel($noEntregado, $tipo);
    // Fuera de la zona y del comercio.
    pedidoEntregadoEn(100);
    incidenciaDelPanel(Pedido::factory()->create(['estado' => EstadoPedido::NoEntregado]), $tipo);

    $this->getJson("/api/panel/indicadores?zona_id={$zona->id}")
        ->assertOk()
        ->assertJsonPath('data.total', 2)
        ->assertJsonPath('data.porcentaje_exito', 50)
        ->assertJsonPath('data.tiempo_promedio_minutos', 40)
        ->assertJsonPath('data.incidencias.por_zona', [['etiqueta' => 'Centro', 'valor' => 1]]);

    $this->getJson("/api/panel/indicadores?comercio_id={$comercio->id}")
        ->assertOk()
        ->assertJsonPath('data.total', 1)
        ->assertJsonPath('data.porcentaje_exito', 100)
        ->assertJsonPath('data.incidencias.por_tipo', []);

    $this->getJson('/api/panel/indicadores?estado=no_entregado')
        ->assertOk()
        ->assertJsonPath('data.total', 2)
        ->assertJsonPath('data.por_estado.entregado', 0)
        ->assertJsonPath('data.porcentaje_exito', 0)
        ->assertJsonPath('data.tiempo_promedio_minutos', null)
        ->assertJsonPath('data.incidencias.por_tipo', [['etiqueta' => 'Cliente ausente', 'valor' => 2]]);
});

test('el panel filtra por la fecha de creación del pedido, con los dos extremos incluidos', function () {
    Sanctum::actingAs($this->operador);
    Pedido::factory()->create(['created_at' => Carbon::parse('2026-11-01 04:59:59')]);
    Pedido::factory()->create(['created_at' => Carbon::parse('2026-11-01 05:00:00')]);
    Pedido::factory()->create(['created_at' => Carbon::parse('2026-11-03 12:00:00')]);
    Pedido::factory()->create(['created_at' => Carbon::parse('2026-11-10 05:00:00')]);

    // El día 1 al 9 de noviembre en Colombia (UTC-5), como lo manda la web.
    $rango = http_build_query(['desde' => '2026-11-01T05:00:00.000Z', 'hasta' => '2026-11-10T04:59:59.999Z']);

    $this->getJson("/api/panel/indicadores?{$rango}")->assertOk()->assertJsonPath('data.total', 2);
});

test('el panel rechaza filtros que no son válidos', function () {
    Sanctum::actingAs($this->operador);

    $this->getJson('/api/panel/indicadores?desde=2026-11-09&hasta=2026-11-01&estado=perdido')
        ->assertUnprocessable()
        ->assertJsonPath('error.codigo', 'VALIDACION')
        ->assertJsonPath('error.campos.hasta.0', 'La fecha hasta no puede ser anterior a la fecha desde.')
        ->assertJsonStructure(['error' => ['campos' => ['estado']]]);
});

test('los indicadores del panel piden iniciar sesión', function () {
    $this->getJson('/api/panel/indicadores')
        ->assertUnauthorized()
        ->assertJsonPath('error.codigo', 'NO_AUTENTICADO');
});

test('comercio y repartidor no pueden ver los indicadores del panel', function (string $rol) {
    Sanctum::actingAs(Usuario::factory()->{$rol}()->create());

    $this->getJson('/api/panel/indicadores')
        ->assertForbidden()
        ->assertJsonPath('error.codigo', 'NO_AUTORIZADO');
})->with(['comercio', 'repartidor']);

test('la lista de pedidos se filtra por comercio (la usa el panel)', function () {
    Sanctum::actingAs($this->operador);
    $comercio = Comercio::factory()->create();
    $propio = Pedido::factory()->create(['comercio_id' => $comercio->id]);
    Pedido::factory()->count(2)->create();

    $this->getJson("/api/pedidos?comercio_id={$comercio->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $propio->id);
});
