<?php

use App\Modules\Administracion\Models\Comercio;
use App\Modules\Administracion\Models\Usuario;
use App\Modules\Incidencias\Models\Incidencia;
use App\Modules\Incidencias\Models\TipoIncidencia;
use App\Modules\Pedidos\Models\Pedido;
use App\Shared\Enums\EstadoIncidencia;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->operador = Usuario::factory()->operador()->create();
    Sanctum::actingAs($this->operador);
});

// ── Lista (INT-12) ───────────────────────────────────────────────────────────

test('la lista trae las incidencias de la más reciente a la más antigua, con su estructura', function () {
    $primera = Incidencia::factory()->create();
    $segunda = Incidencia::factory()->create();

    $this->getJson('/api/incidencias')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $segunda->id)
        ->assertJsonPath('data.1.id', $primera->id)
        ->assertJsonStructure([
            'data' => [[
                'id', 'pedido_id', 'descripcion', 'foto_url', 'estado', 'prioridad', 'resolucion', 'nota_comercio',
                'created_at', 'pedido' => ['id', 'numero'], 'tipo' => ['id', 'nombre'], 'usuario' => ['id', 'nombre'],
            ]],
            'meta',
        ]);
});

test('la lista filtra por estado, tipo, prioridad y número de pedido', function () {
    $tipo = TipoIncidencia::factory()->create();
    $pedido = Pedido::factory()->create(['numero' => 'PED-0131']);
    $buscada = Incidencia::factory()->enRevision()->for($pedido)->create([
        'tipo_incidencia_id' => $tipo->id,
        'prioridad' => 'alta',
    ]);
    Incidencia::factory()->create(['prioridad' => 'baja']);

    foreach (['estado=en_revision', "tipo_incidencia_id={$tipo->id}", 'prioridad=alta', 'numero=ped-013'] as $filtro) {
        $this->getJson("/api/incidencias?{$filtro}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $buscada->id);
    }

    $this->getJson('/api/incidencias?estado=resuelta')->assertOk()->assertJsonCount(0, 'data');
});

test('un filtro con un valor que no existe responde error de validación', function () {
    $this->getJson('/api/incidencias?estado=cerrada&prioridad=urgente')
        ->assertUnprocessable()
        ->assertJsonPath('error.codigo', 'VALIDACION')
        ->assertJsonPath('error.campos.estado.0', 'El valor elegido para estado no es válido.')
        ->assertJsonStructure(['error' => ['campos' => ['prioridad']]]);
});

test('el comercio solo ve las incidencias de sus pedidos, en la lista y en el detalle', function () {
    $comercio = Comercio::factory()->create();
    $propia = Incidencia::factory()->for(Pedido::factory()->for($comercio))->create(['nota_comercio' => 'Para ti.']);
    $ajena = Incidencia::factory()->create();
    Sanctum::actingAs(Usuario::factory()->comercio($comercio)->create());

    $this->getJson('/api/incidencias')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $propia->id);

    $this->getJson("/api/incidencias/{$propia->id}")
        ->assertOk()
        ->assertJsonPath('data.nota_comercio', 'Para ti.');

    $this->getJson("/api/incidencias/{$ajena->id}")
        ->assertNotFound()
        ->assertJsonPath('error.codigo', 'NO_ENCONTRADO');
});

// ── Detalle y gestión (INT-13) ───────────────────────────────────────────────

test('el detalle trae el pedido, el tipo y quién la registró', function () {
    $incidencia = Incidencia::factory()->create(['descripcion' => 'No atendió el timbre.']);

    $this->getJson("/api/incidencias/{$incidencia->id}")
        ->assertOk()
        ->assertJsonPath('data.descripcion', 'No atendió el timbre.')
        ->assertJsonPath('data.estado', 'abierta')
        ->assertJsonPath('data.pedido.id', $incidencia->pedido_id)
        ->assertJsonPath('data.usuario.id', $incidencia->usuario_id);

    $this->getJson('/api/incidencias/9999')->assertNotFound();
});

test('se asigna la prioridad de la incidencia', function () {
    $incidencia = Incidencia::factory()->create();

    $this->putJson("/api/incidencias/{$incidencia->id}/prioridad", ['prioridad' => 'alta'])
        ->assertOk()
        ->assertJsonPath('data.prioridad', 'alta');

    expect($incidencia->fresh()->prioridad->value)->toBe('alta');

    $this->putJson("/api/incidencias/{$incidencia->id}/prioridad", ['prioridad' => 'urgente'])
        ->assertUnprocessable()
        ->assertJsonPath('error.campos.prioridad.0', 'El valor elegido para prioridad no es válido.');

    $this->putJson("/api/incidencias/{$incidencia->id}/prioridad", [])
        ->assertUnprocessable()
        ->assertJsonPath('error.campos.prioridad.0', 'Elige la prioridad.');

    expect($incidencia->fresh()->prioridad->value)->toBe('alta');
});

test('la incidencia pasa de abierta a en revisión y de en revisión a resuelta', function () {
    $incidencia = Incidencia::factory()->create();

    $this->putJson("/api/incidencias/{$incidencia->id}/estado", ['estado' => 'en_revision'])
        ->assertOk()
        ->assertJsonPath('data.estado', 'en_revision')
        ->assertJsonPath('data.resolucion', null);

    $this->putJson("/api/incidencias/{$incidencia->id}/estado", [
        'estado' => 'resuelta',
        'resolucion' => 'Se reprogramó la entrega para las 15:00.',
    ])
        ->assertOk()
        ->assertJsonPath('data.estado', 'resuelta')
        ->assertJsonPath('data.resolucion', 'Se reprogramó la entrega para las 15:00.');

    expect($incidencia->fresh()->estado)->toBe(EstadoIncidencia::Resuelta);
});

test('para resolver una incidencia la resolución es obligatoria', function (array $datos) {
    $incidencia = Incidencia::factory()->enRevision()->create();

    $this->putJson("/api/incidencias/{$incidencia->id}/estado", ['estado' => 'resuelta', ...$datos])
        ->assertUnprocessable()
        ->assertJsonPath('error.codigo', 'VALIDACION')
        ->assertJsonPath('error.campos.resolucion.0', 'Escribe la resolución para marcar la incidencia como resuelta.');

    expect($incidencia->fresh()->estado)->toBe(EstadoIncidencia::EnRevision)
        ->and($incidencia->fresh()->resolucion)->toBeNull();
})->with([
    'sin resolución' => [[]],
    'solo espacios' => [['resolucion' => '   ']],
]);

test('no se permite saltar ni devolver el estado de una incidencia', function (string $actual, string $nuevo) {
    $incidencia = Incidencia::factory()->create(['estado' => $actual]);

    $this->putJson("/api/incidencias/{$incidencia->id}/estado", ['estado' => $nuevo, 'resolucion' => 'Listo.'])
        ->assertStatus(409)
        ->assertJsonPath('error.codigo', 'TRANSICION_NO_PERMITIDA');

    expect($incidencia->fresh()->estado->value)->toBe($actual)
        ->and($incidencia->fresh()->resolucion)->toBeNull();
})->with([
    'abierta → resuelta' => ['abierta', 'resuelta'],
    'en revisión → en revisión' => ['en_revision', 'en_revision'],
    'resuelta → en revisión' => ['resuelta', 'en_revision'],
    'resuelta → resuelta' => ['resuelta', 'resuelta'],
]);

test('no se puede volver una incidencia a abierta', function () {
    $incidencia = Incidencia::factory()->enRevision()->create();

    $this->putJson("/api/incidencias/{$incidencia->id}/estado", ['estado' => 'abierta'])
        ->assertUnprocessable()
        ->assertJsonPath('error.campos.estado.0', 'El valor elegido para estado no es válido.');

    expect($incidencia->fresh()->estado)->toBe(EstadoIncidencia::EnRevision);
});

test('la nota para el comercio es una sola y se puede editar', function () {
    $incidencia = Incidencia::factory()->create();

    $this->putJson("/api/incidencias/{$incidencia->id}/nota", ['nota_comercio' => 'Intentaremos de nuevo en la tarde.'])
        ->assertOk()
        ->assertJsonPath('data.nota_comercio', 'Intentaremos de nuevo en la tarde.');

    $this->putJson("/api/incidencias/{$incidencia->id}/nota", ['nota_comercio' => 'Entregado al segundo intento.'])
        ->assertOk()
        ->assertJsonPath('data.nota_comercio', 'Entregado al segundo intento.');

    expect($incidencia->fresh()->nota_comercio)->toBe('Entregado al segundo intento.');
});

test('la nota para el comercio no puede quedar vacía', function (array $datos) {
    $incidencia = Incidencia::factory()->create(['nota_comercio' => 'Nota vigente.']);

    $this->putJson("/api/incidencias/{$incidencia->id}/nota", $datos)
        ->assertUnprocessable()
        ->assertJsonPath('error.codigo', 'VALIDACION')
        ->assertJsonPath('error.campos.nota_comercio.0', 'La nota no puede estar vacía.');

    expect($incidencia->fresh()->nota_comercio)->toBe('Nota vigente.');
})->with([
    'sin nota' => [[]],
    'vacía' => [['nota_comercio' => '']],
    'solo espacios' => [['nota_comercio' => '   ']],
]);

test('gestionar una incidencia que no existe responde 404', function (string $accion, array $datos) {
    $this->putJson("/api/incidencias/9999/{$accion}", $datos)
        ->assertNotFound()
        ->assertJsonPath('error.codigo', 'NO_ENCONTRADO');
})->with('acciones de incidencia');

// ── Registrar desde el detalle del pedido (INT-19 web) ───────────────────────

test('el operador registra una incidencia desde el pedido, en cualquier estado, y queda abierta', function (string $rol, string $estado) {
    Storage::fake('public');
    $usuario = Usuario::factory()->{$rol}()->create();
    Sanctum::actingAs($usuario);
    $pedido = Pedido::factory()->create(['estado' => $estado]);
    $tipo = TipoIncidencia::factory()->create(['nombre' => 'Dirección no localizada']);

    $this->post("/api/pedidos/{$pedido->id}/incidencias", [
        'tipo_incidencia_id' => $tipo->id,
        'descripcion' => 'El número de apartamento no existe.',
        'foto' => UploadedFile::fake()->create('foto.jpg', 300, 'image/jpeg'),
    ], ['Accept' => 'application/json'])
        ->assertCreated()
        ->assertJsonPath('data.estado', 'abierta')
        ->assertJsonPath('data.prioridad', null)
        ->assertJsonPath('data.tipo.nombre', 'Dirección no localizada')
        ->assertJsonPath('data.usuario.id', $usuario->id);

    $incidencia = Incidencia::query()->sole();

    expect($incidencia->pedido_id)->toBe($pedido->id);
    Storage::disk('public')->assertExists($incidencia->ruta_foto);
})->with(['administrador', 'operador'])->with(['creado', 'en_ruta', 'entregado']);

test('registrar desde el pedido exige tipo activo y descripción', function () {
    $pedido = Pedido::factory()->create();

    $this->postJson("/api/pedidos/{$pedido->id}/incidencias", [])
        ->assertUnprocessable()
        ->assertJsonPath('error.campos.tipo_incidencia_id.0', 'Elige el tipo de la incidencia.')
        ->assertJsonPath('error.campos.descripcion.0', 'Escribe qué ocurrió.');

    $this->postJson('/api/pedidos/9999/incidencias', [
        'tipo_incidencia_id' => TipoIncidencia::factory()->create()->id,
        'descripcion' => 'Algo pasó.',
    ])->assertNotFound();

    expect(Incidencia::query()->count())->toBe(0);
});

// ── Permisos ─────────────────────────────────────────────────────────────────

dataset('acciones de incidencia', [
    'prioridad' => ['prioridad', ['prioridad' => 'alta']],
    'estado' => ['estado', ['estado' => 'en_revision']],
    'nota' => ['nota', ['nota_comercio' => 'Una nota.']],
]);

test('las incidencias responden 401 sin token', function (string $metodo, string $ruta) {
    $this->app['auth']->forgetGuards();

    $this->json($metodo, $ruta)
        ->assertUnauthorized()
        ->assertJsonPath('error.codigo', 'NO_AUTENTICADO');
})->with([
    'detalle' => ['GET', '/api/incidencias/1'],
    'prioridad' => ['PUT', '/api/incidencias/1/prioridad'],
    'estado' => ['PUT', '/api/incidencias/1/estado'],
    'nota' => ['PUT', '/api/incidencias/1/nota'],
]);

test('comercio y repartidor no gestionan incidencias', function (string $rol, string $accion, array $datos) {
    $incidencia = Incidencia::factory()->create();
    Sanctum::actingAs(Usuario::factory()->{$rol}()->create());

    $this->putJson("/api/incidencias/{$incidencia->id}/{$accion}", $datos)
        ->assertForbidden()
        ->assertJsonPath('error.codigo', 'NO_AUTORIZADO');

    $sinCambios = $incidencia->fresh();

    expect($sinCambios->estado)->toBe(EstadoIncidencia::Abierta)
        ->and($sinCambios->prioridad)->toBeNull()
        ->and($sinCambios->nota_comercio)->toBeNull();
})->with(['comercio', 'repartidor'])->with('acciones de incidencia');

test('el comercio no registra incidencias', function () {
    $comercio = Comercio::factory()->create();
    $pedido = Pedido::factory()->for($comercio)->create();
    Sanctum::actingAs(Usuario::factory()->comercio($comercio)->create());

    $this->postJson("/api/pedidos/{$pedido->id}/incidencias", [])
        ->assertForbidden()
        ->assertJsonPath('error.codigo', 'NO_AUTORIZADO');
});

test('el repartidor solo abre el detalle de las incidencias de sus pedidos y no ve la nota del comercio', function () {
    $repartidor = Usuario::factory()->repartidor()->create();
    $propia = Incidencia::factory()->for(Pedido::factory()->asignadoA($repartidor))->create(['nota_comercio' => 'Solo comercio.']);
    $ajena = Incidencia::factory()->create();
    Sanctum::actingAs($repartidor);

    $this->getJson("/api/incidencias/{$propia->id}")->assertOk()->assertJsonMissingPath('data.nota_comercio');
    $this->getJson("/api/incidencias/{$ajena->id}")->assertNotFound();
});
