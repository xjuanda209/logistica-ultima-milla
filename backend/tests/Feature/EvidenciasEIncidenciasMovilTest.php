<?php

use App\Modules\Administracion\Models\Usuario;
use App\Modules\Despacho\Models\Evidencia;
use App\Modules\Incidencias\Models\Incidencia;
use App\Modules\Incidencias\Models\TipoIncidencia;
use App\Modules\Pedidos\Models\Pedido;
use App\Shared\Enums\EstadoIncidencia;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Storage::fake('public');

    $this->repartidor = Usuario::factory()->repartidor()->create();
    $this->pedido = Pedido::factory()->asignadoA($this->repartidor)->create(['estado' => 'en_ruta']);
    $this->tipo = TipoIncidencia::factory()->create(['nombre' => 'Cliente ausente']);
    Sanctum::actingAs($this->repartidor);
});

function imagenDePrueba(string $nombre = 'foto.jpg', int $kilobytes = 300, string $mime = 'image/jpeg'): UploadedFile
{
    return UploadedFile::fake()->create($nombre, $kilobytes, $mime);
}

// ── Evidencias ───────────────────────────────────────────────────────────────

test('el repartidor registra una evidencia con ubicación: el archivo va al disco y la tabla guarda la ruta', function (string $estado) {
    $this->pedido->update(['estado' => $estado]);

    $this->post("/api/pedidos/{$this->pedido->id}/evidencias", [
        'tipo' => 'foto_entrega',
        'archivo' => imagenDePrueba(),
        'latitud' => 4.813333,
        'longitud' => -75.696111,
    ], ['Accept' => 'application/json'])
        ->assertCreated()
        ->assertJsonCount(1, 'data.evidencias')
        ->assertJsonPath('data.evidencias.0.tipo', 'foto_entrega')
        ->assertJsonPath('data.evidencias.0.con_ubicacion', true);

    $evidencia = Evidencia::query()->sole();

    expect($evidencia->pedido_id)->toBe($this->pedido->id)
        ->and($evidencia->usuario_id)->toBe($this->repartidor->id)
        ->and($evidencia->ruta_archivo)->toStartWith("evidencias/{$this->pedido->id}/")
        ->and((float) $evidencia->latitud)->toBe(4.813333);

    Storage::disk('public')->assertExists($evidencia->ruta_archivo);
})->with(['recogido', 'en_ruta']);

test('sin permiso de ubicación la evidencia se guarda sin coordenadas', function () {
    $this->post("/api/pedidos/{$this->pedido->id}/evidencias", [
        'tipo' => 'comprobante',
        'archivo' => imagenDePrueba('comprobante.png', 200, 'image/png'),
    ], ['Accept' => 'application/json'])
        ->assertCreated()
        ->assertJsonPath('data.evidencias.0.con_ubicacion', false);

    expect(Evidencia::query()->sole()->latitud)->toBeNull();
});

test('la evidencia debe ser JPG o PNG de máximo 5 MB', function (UploadedFile $archivo) {
    $this->post("/api/pedidos/{$this->pedido->id}/evidencias", ['tipo' => 'foto_entrega', 'archivo' => $archivo], ['Accept' => 'application/json'])
        ->assertStatus(422)
        ->assertJsonPath('error.codigo', 'VALIDACION')
        ->assertJsonPath('error.campos.archivo.0', 'La imagen debe ser JPG o PNG y pesar 5 MB o menos.');

    expect(Evidencia::query()->count())->toBe(0);
})->with([
    'pdf' => [fn () => imagenDePrueba('factura.pdf', 100, 'application/pdf')],
    'gif' => [fn () => imagenDePrueba('animacion.gif', 100, 'image/gif')],
    'más de 5 MB' => [fn () => imagenDePrueba('grande.jpg', 5121)],
]);

test('la evidencia exige tipo válido y archivo', function () {
    $this->postJson("/api/pedidos/{$this->pedido->id}/evidencias", ['tipo' => 'selfie'])
        ->assertStatus(422)
        ->assertJsonPath('error.campos.tipo.0', 'El valor elegido para tipo no es válido.')
        ->assertJsonPath('error.campos.archivo.0', 'Toma una foto o elige una imagen de la galería.');
});

test('solo se registran evidencias en recogido o en ruta', function (string $estado) {
    $this->pedido->update(['estado' => $estado]);

    $this->post("/api/pedidos/{$this->pedido->id}/evidencias", ['tipo' => 'foto_entrega', 'archivo' => imagenDePrueba()], ['Accept' => 'application/json'])
        ->assertStatus(409)
        ->assertJsonPath('error.codigo', 'PEDIDO_NO_ADMITE_EVIDENCIAS');

    expect(Evidencia::query()->count())->toBe(0);
})->with(['asignado', 'entregado', 'no_entregado', 'cancelado']);

test('no se registran evidencias en el pedido de otro repartidor', function () {
    $ajeno = Pedido::factory()->asignadoA(Usuario::factory()->repartidor()->create())->create(['estado' => 'en_ruta']);

    $this->post("/api/pedidos/{$ajeno->id}/evidencias", ['tipo' => 'foto_entrega', 'archivo' => imagenDePrueba()], ['Accept' => 'application/json'])
        ->assertNotFound()
        ->assertJsonPath('error.codigo', 'NO_ENCONTRADO');

    expect(Evidencia::query()->count())->toBe(0);
});

// ── Incidencias ──────────────────────────────────────────────────────────────

test('el repartidor registra una incidencia con foto y queda abierta', function () {
    $this->post("/api/pedidos/{$this->pedido->id}/incidencias", [
        'tipo_incidencia_id' => $this->tipo->id,
        'descripcion' => 'El cliente no atendió el timbre.',
        'foto' => imagenDePrueba(),
    ], ['Accept' => 'application/json'])
        ->assertCreated()
        ->assertJsonPath('data.estado', 'abierta')
        ->assertJsonPath('data.prioridad', null)
        ->assertJsonPath('data.tipo.nombre', 'Cliente ausente')
        ->assertJsonMissingPath('data.nota_comercio');

    $incidencia = Incidencia::query()->sole();

    expect($incidencia->estado)->toBe(EstadoIncidencia::Abierta)
        ->and($incidencia->usuario_id)->toBe($this->repartidor->id)
        ->and($incidencia->pedido_id)->toBe($this->pedido->id);

    Storage::disk('public')->assertExists($incidencia->ruta_foto);
});

test('la foto de la incidencia es opcional', function () {
    $this->postJson("/api/pedidos/{$this->pedido->id}/incidencias", [
        'tipo_incidencia_id' => $this->tipo->id,
        'descripcion' => 'Dirección incompleta.',
    ])->assertCreated()->assertJsonPath('data.foto_url', null);
});

test('la incidencia exige tipo activo y descripción', function () {
    $inactivo = TipoIncidencia::factory()->create(['activo' => false]);
    $ruta = "/api/pedidos/{$this->pedido->id}/incidencias";

    $this->postJson($ruta, [])
        ->assertStatus(422)
        ->assertJsonPath('error.campos.tipo_incidencia_id.0', 'Elige el tipo de la incidencia.')
        ->assertJsonPath('error.campos.descripcion.0', 'Escribe qué ocurrió.');

    $this->postJson($ruta, ['tipo_incidencia_id' => $inactivo->id, 'descripcion' => 'Algo pasó.'])
        ->assertStatus(422)
        ->assertJsonPath('error.campos.tipo_incidencia_id.0', 'Elige el tipo de la incidencia.');

    $this->post($ruta, ['tipo_incidencia_id' => $this->tipo->id, 'descripcion' => 'Algo pasó.', 'foto' => imagenDePrueba('nota.pdf', 50, 'application/pdf')], ['Accept' => 'application/json'])
        ->assertStatus(422)
        ->assertJsonPath('error.campos.foto.0', 'La imagen debe ser JPG o PNG y pesar 5 MB o menos.');

    expect(Incidencia::query()->count())->toBe(0);
});

test('solo se registran incidencias en recogido o en ruta', function (string $estado) {
    $this->pedido->update(['estado' => $estado]);

    $this->postJson("/api/pedidos/{$this->pedido->id}/incidencias", ['tipo_incidencia_id' => $this->tipo->id, 'descripcion' => 'Algo pasó.'])
        ->assertStatus(409)
        ->assertJsonPath('error.codigo', 'PEDIDO_NO_ADMITE_INCIDENCIAS');

    expect(Incidencia::query()->count())->toBe(0);
})->with(['asignado', 'entregado', 'no_entregado', 'cancelado']);

test('no se registran incidencias en el pedido de otro repartidor', function () {
    $ajeno = Pedido::factory()->asignadoA(Usuario::factory()->repartidor()->create())->create(['estado' => 'en_ruta']);

    $this->postJson("/api/pedidos/{$ajeno->id}/incidencias", ['tipo_incidencia_id' => $this->tipo->id, 'descripcion' => 'Algo pasó.'])
        ->assertNotFound();

    expect(Incidencia::query()->count())->toBe(0);
});

// ── Permisos y lectura ───────────────────────────────────────────────────────

test('evidencias e incidencias de la app exigen autenticación', function (string $ruta) {
    $this->app['auth']->forgetGuards();

    $this->postJson("/api/pedidos/{$this->pedido->id}/{$ruta}", [])
        ->assertUnauthorized()
        ->assertJsonPath('error.codigo', 'NO_AUTENTICADO');
})->with(['evidencias', 'incidencias']);

test('solo el repartidor registra evidencias', function (string $rol) {
    Sanctum::actingAs(Usuario::factory()->{$rol}()->create());

    $this->postJson("/api/pedidos/{$this->pedido->id}/evidencias", [])
        ->assertForbidden()
        ->assertJsonPath('error.codigo', 'NO_AUTORIZADO');
})->with(['administrador', 'operador', 'comercio']);

// Administrador y operador sí registran incidencias, desde la web: ver IncidenciasTest.
test('el comercio no registra incidencias desde la app', function () {
    Sanctum::actingAs(Usuario::factory()->comercio()->create());

    $this->postJson("/api/pedidos/{$this->pedido->id}/incidencias", [])
        ->assertForbidden()
        ->assertJsonPath('error.codigo', 'NO_AUTORIZADO');
});

test('el detalle trae evidencias e incidencias para consulta y el repartidor no ve la nota del comercio', function () {
    $this->pedido->evidencias()->create([
        'usuario_id' => $this->repartidor->id, 'tipo' => 'comprobante',
        'ruta_archivo' => 'evidencias/1/comprobante.jpg', 'created_at' => now(),
    ]);
    $this->pedido->incidencias()->create([
        'tipo_incidencia_id' => $this->tipo->id, 'usuario_id' => $this->repartidor->id,
        'descripcion' => 'No atendió.', 'prioridad' => 'alta', 'nota_comercio' => 'Nota solo para el comercio',
    ]);

    $detalle = $this->getJson("/api/pedidos/{$this->pedido->id}")
        ->assertOk()
        ->assertJsonPath('data.evidencias.0.url', asset('storage/evidencias/1/comprobante.jpg'))
        ->assertJsonPath('data.incidencias.0.descripcion', 'No atendió.')
        ->assertJsonPath('data.incidencias.0.prioridad', 'alta')
        ->assertJsonMissingPath('data.incidencias.0.nota_comercio');

    expect($detalle->getContent())->not->toContain('Nota solo para el comercio');

    $lista = $this->getJson('/api/incidencias')->assertOk()->assertJsonMissingPath('data.0.nota_comercio');

    expect($lista->getContent())->not->toContain('Nota solo para el comercio');

    Sanctum::actingAs(Usuario::factory()->operador()->create());

    $this->getJson('/api/incidencias')->assertJsonPath('data.0.nota_comercio', 'Nota solo para el comercio');
});
