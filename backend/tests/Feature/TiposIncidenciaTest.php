<?php

use App\Modules\Administracion\Models\Usuario;
use App\Modules\Incidencias\Models\TipoIncidencia;
use App\Shared\Enums\Rol;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->administrador = Usuario::factory()->administrador()->create();
});

test('el administrador lista los tipos de incidencia, activos e inactivos', function () {
    Sanctum::actingAs($this->administrador);
    TipoIncidencia::factory()->create(['nombre' => 'Paquete dañado']);
    TipoIncidencia::factory()->inactivo()->create(['nombre' => 'Cliente ausente']);

    $this->getJson('/api/tipos-incidencia')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.nombre', 'Cliente ausente')
        ->assertJsonPath('data.0.activo', false)
        ->assertJsonStructure(['data' => [['id', 'nombre', 'activo']], 'meta']);
});

test('el administrador crea un tipo de incidencia y queda activo', function () {
    Sanctum::actingAs($this->administrador);

    $this->postJson('/api/tipos-incidencia', ['nombre' => 'Vía cerrada', 'activo' => false])
        ->assertCreated()
        ->assertJsonPath('data.nombre', 'Vía cerrada')
        ->assertJsonPath('data.activo', true);
});

test('crear un tipo de incidencia exige un nombre de máximo 60 caracteres', function () {
    Sanctum::actingAs($this->administrador);

    $this->postJson('/api/tipos-incidencia', [])
        ->assertUnprocessable()
        ->assertJsonPath('error.codigo', 'VALIDACION')
        ->assertJsonPath('error.campos.nombre.0', 'El campo nombre es obligatorio.');

    $this->postJson('/api/tipos-incidencia', ['nombre' => str_repeat('a', 61)])
        ->assertUnprocessable()
        ->assertJsonStructure(['error' => ['campos' => ['nombre']]]);
});

test('el nombre del tipo de incidencia es único', function () {
    Sanctum::actingAs($this->administrador);
    TipoIncidencia::factory()->create(['nombre' => 'Otro']);

    $this->postJson('/api/tipos-incidencia', ['nombre' => 'Otro'])
        ->assertUnprocessable()
        ->assertJsonPath('error.campos.nombre.0', 'Ya existe un tipo de incidencia con ese nombre.');

    expect(TipoIncidencia::query()->count())->toBe(1);
});

test('el nombre único del tipo de incidencia no distingue mayúsculas de minúsculas', function (string $nombre) {
    Sanctum::actingAs($this->administrador);
    TipoIncidencia::factory()->create(['nombre' => 'Paquete roto']);
    $otro = TipoIncidencia::factory()->create(['nombre' => 'Otro']);

    $this->postJson('/api/tipos-incidencia', ['nombre' => $nombre])
        ->assertUnprocessable()
        ->assertJsonPath('error.codigo', 'VALIDACION')
        ->assertJsonPath('error.campos.nombre.0', 'Ya existe un tipo de incidencia con ese nombre.');

    $this->putJson("/api/tipos-incidencia/{$otro->id}", ['nombre' => $nombre])
        ->assertUnprocessable()
        ->assertJsonPath('error.campos.nombre.0', 'Ya existe un tipo de incidencia con ese nombre.');

    expect(TipoIncidencia::query()->count())->toBe(2)
        ->and($otro->fresh()->nombre)->toBe('Otro');
})->with(['paquete roto', 'PAQUETE ROTO', 'Paquete Roto']);

test('un tipo de incidencia puede cambiar solo las mayúsculas de su propio nombre', function () {
    Sanctum::actingAs($this->administrador);
    $tipo = TipoIncidencia::factory()->create(['nombre' => 'paquete roto']);

    $this->putJson("/api/tipos-incidencia/{$tipo->id}", ['nombre' => 'Paquete roto'])
        ->assertOk()
        ->assertJsonPath('data.nombre', 'Paquete roto');
});

test('el administrador edita un tipo de incidencia sin cambiar su estado', function () {
    Sanctum::actingAs($this->administrador);
    $tipo = TipoIncidencia::factory()->inactivo()->create(['nombre' => 'Otro']);

    $this->putJson("/api/tipos-incidencia/{$tipo->id}", ['nombre' => 'Otro'])->assertOk();

    $this->putJson("/api/tipos-incidencia/{$tipo->id}", ['nombre' => 'Otro motivo', 'activo' => true])
        ->assertOk()
        ->assertJsonPath('data.nombre', 'Otro motivo')
        ->assertJsonPath('data.activo', false);
});

test('editar un tipo de incidencia no permite usar el nombre de otro', function () {
    Sanctum::actingAs($this->administrador);
    TipoIncidencia::factory()->create(['nombre' => 'Otro']);
    $tipo = TipoIncidencia::factory()->create(['nombre' => 'Vía cerrada']);

    $this->putJson("/api/tipos-incidencia/{$tipo->id}", ['nombre' => 'Otro'])
        ->assertUnprocessable()
        ->assertJsonStructure(['error' => ['campos' => ['nombre']]]);
});

test('el administrador desactiva y reactiva un tipo de incidencia', function () {
    Sanctum::actingAs($this->administrador);
    $tipo = TipoIncidencia::factory()->create();

    $this->putJson("/api/tipos-incidencia/{$tipo->id}/estado", ['activo' => false])
        ->assertOk()
        ->assertJsonPath('data.activo', false);

    $this->putJson("/api/tipos-incidencia/{$tipo->id}/estado", ['activo' => true])
        ->assertOk()
        ->assertJsonPath('data.activo', true);

    $this->putJson("/api/tipos-incidencia/{$tipo->id}/estado", ['activo' => 'quizás'])
        ->assertUnprocessable()
        ->assertJsonPath('error.campos.activo.0', 'El campo activo debe ser verdadero o falso.');
});

test('un tipo de incidencia que no existe responde 404', function () {
    Sanctum::actingAs($this->administrador);

    $this->putJson('/api/tipos-incidencia/9999', ['nombre' => 'Otro'])->assertNotFound();
    $this->putJson('/api/tipos-incidencia/9999/estado', ['activo' => false])
        ->assertNotFound()
        ->assertJsonPath('error.codigo', 'NO_ENCONTRADO');
});

dataset('rutas de tipos de incidencia', [
    'listar' => ['GET', '/api/tipos-incidencia'],
    'crear' => ['POST', '/api/tipos-incidencia'],
    'editar' => ['PUT', '/api/tipos-incidencia/1'],
    'cambiar estado' => ['PUT', '/api/tipos-incidencia/1/estado'],
]);

test('los tipos de incidencia responden 401 sin token', function (string $metodo, string $ruta) {
    $this->json($metodo, $ruta)
        ->assertUnauthorized()
        ->assertJsonPath('error.codigo', 'NO_AUTENTICADO');
})->with('rutas de tipos de incidencia');

test('cualquier usuario autenticado puede listar los tipos de incidencia', function (string $rol) {
    TipoIncidencia::factory()->count(2)->create();
    Sanctum::actingAs(Usuario::factory()->{$rol}()->create());

    $this->getJson('/api/tipos-incidencia')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonStructure(['data' => [['id', 'nombre', 'activo']]]);
})->with(['operador', 'comercio', 'repartidor']);

test('los tipos de incidencia se pueden filtrar con activos=1', function () {
    Sanctum::actingAs(Usuario::factory()->repartidor()->create());
    $activo = TipoIncidencia::factory()->create();
    TipoIncidencia::factory()->inactivo()->count(2)->create();

    $this->getJson('/api/tipos-incidencia')->assertOk()->assertJsonCount(3, 'data');
    $this->getJson('/api/tipos-incidencia?activos=0')->assertOk()->assertJsonCount(3, 'data');

    $this->getJson('/api/tipos-incidencia?activos=1')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $activo->id)
        ->assertJsonPath('meta.total', 1);

    $this->getJson('/api/tipos-incidencia?activos=quizas')
        ->assertUnprocessable()
        ->assertJsonStructure(['error' => ['campos' => ['activos']]]);
});

test('la lista de tipos de incidencia acepta por_pagina entre 1 y 100', function () {
    Sanctum::actingAs($this->administrador);
    TipoIncidencia::factory()->count(25)->create();

    $this->getJson('/api/tipos-incidencia')->assertOk()->assertJsonCount(20, 'data');
    $this->getJson('/api/tipos-incidencia?por_pagina=100&activos=1')->assertOk()->assertJsonCount(25, 'data');
    $this->getJson('/api/tipos-incidencia?por_pagina=101')->assertUnprocessable();
});

test('solo el administrador puede crear, editar y activar o desactivar tipos de incidencia', function (string $metodo, string $ruta) {
    foreach ([Rol::Operador, Rol::Comercio, Rol::Repartidor] as $rol) {
        Sanctum::actingAs(Usuario::factory()->{$rol->value}()->create());

        $this->json($metodo, $ruta, ['nombre' => 'Intruso', 'activo' => false])
            ->assertForbidden()
            ->assertJsonPath('error.codigo', 'NO_AUTORIZADO');
    }

    expect(TipoIncidencia::query()->count())->toBe(0);
})->with([
    'crear' => ['POST', '/api/tipos-incidencia'],
    'editar' => ['PUT', '/api/tipos-incidencia/1'],
    'cambiar estado' => ['PUT', '/api/tipos-incidencia/1/estado'],
]);
