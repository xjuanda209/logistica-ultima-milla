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

test('solo el administrador puede administrar tipos de incidencia', function (string $metodo, string $ruta) {
    foreach ([Rol::Operador, Rol::Comercio, Rol::Repartidor] as $rol) {
        Sanctum::actingAs(Usuario::factory()->{$rol->value}()->create());

        $this->json($metodo, $ruta, ['nombre' => 'Intruso', 'activo' => false])
            ->assertForbidden()
            ->assertJsonPath('error.codigo', 'NO_AUTORIZADO');
    }

    expect(TipoIncidencia::query()->count())->toBe(0);
})->with('rutas de tipos de incidencia');
