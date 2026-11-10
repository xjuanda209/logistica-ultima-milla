<?php

use App\Modules\Administracion\Models\Usuario;
use App\Modules\Administracion\Models\Zona;
use App\Shared\Enums\Rol;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->administrador = Usuario::factory()->administrador()->create();
});

test('el administrador lista las zonas ordenadas por nombre', function () {
    Sanctum::actingAs($this->administrador);
    Zona::factory()->create(['nombre' => 'Sur']);
    Zona::factory()->create(['nombre' => 'Centro']);

    $this->getJson('/api/zonas')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.nombre', 'Centro')
        ->assertJsonStructure(['data' => [['id', 'nombre', 'descripcion']], 'meta']);
});

test('la lista de zonas acepta por_pagina entre 1 y 100', function () {
    Sanctum::actingAs($this->administrador);
    Zona::factory()->count(25)->create();

    $this->getJson('/api/zonas')->assertOk()->assertJsonCount(20, 'data');
    $this->getJson('/api/zonas?por_pagina=100')->assertOk()->assertJsonCount(25, 'data');
    $this->getJson('/api/zonas?por_pagina=0')
        ->assertUnprocessable()
        ->assertJsonStructure(['error' => ['campos' => ['por_pagina']]]);
});

test('el administrador crea una zona', function () {
    Sanctum::actingAs($this->administrador);

    $this->postJson('/api/zonas', ['nombre' => 'Oriente', 'descripcion' => 'Barrios del oriente'])
        ->assertCreated()
        ->assertJsonPath('data.nombre', 'Oriente')
        ->assertJsonPath('data.descripcion', 'Barrios del oriente');

    expect(Zona::query()->where('nombre', 'Oriente')->exists())->toBeTrue();
});

test('crear una zona exige un nombre de máximo 60 caracteres', function () {
    Sanctum::actingAs($this->administrador);

    $this->postJson('/api/zonas', [])
        ->assertUnprocessable()
        ->assertJsonPath('error.codigo', 'VALIDACION')
        ->assertJsonPath('error.campos.nombre.0', 'El campo nombre es obligatorio.');

    $this->postJson('/api/zonas', ['nombre' => str_repeat('a', 61)])
        ->assertUnprocessable()
        ->assertJsonPath('error.campos.nombre.0', 'El campo nombre no puede tener más de 60 caracteres.');
});

test('el nombre de la zona es único', function () {
    Sanctum::actingAs($this->administrador);
    Zona::factory()->create(['nombre' => 'Centro']);

    $this->postJson('/api/zonas', ['nombre' => 'Centro'])
        ->assertUnprocessable()
        ->assertJsonPath('error.campos.nombre.0', 'Ya existe una zona con ese nombre.');

    expect(Zona::query()->count())->toBe(1);
});

test('el administrador edita una zona y puede conservar su nombre', function () {
    Sanctum::actingAs($this->administrador);
    $zona = Zona::factory()->create(['nombre' => 'Centro']);

    $this->putJson("/api/zonas/{$zona->id}", ['nombre' => 'Centro', 'descripcion' => 'Nueva descripción'])
        ->assertOk()
        ->assertJsonPath('data.descripcion', 'Nueva descripción');

    $this->putJson("/api/zonas/{$zona->id}", ['nombre' => 'Centro histórico'])
        ->assertOk()
        ->assertJsonPath('data.nombre', 'Centro histórico');
});

test('editar una zona no permite usar el nombre de otra', function () {
    Sanctum::actingAs($this->administrador);
    Zona::factory()->create(['nombre' => 'Centro']);
    $zona = Zona::factory()->create(['nombre' => 'Norte']);

    $this->putJson("/api/zonas/{$zona->id}", ['nombre' => 'Centro'])
        ->assertUnprocessable()
        ->assertJsonStructure(['error' => ['campos' => ['nombre']]]);

    expect($zona->fresh()->nombre)->toBe('Norte');
});

test('editar una zona que no existe responde 404', function () {
    Sanctum::actingAs($this->administrador);

    $this->putJson('/api/zonas/9999', ['nombre' => 'Centro'])
        ->assertNotFound()
        ->assertJsonPath('error.codigo', 'NO_ENCONTRADO');
});

dataset('rutas de zonas', [
    'listar' => ['GET', '/api/zonas'],
    'crear' => ['POST', '/api/zonas'],
    'editar' => ['PUT', '/api/zonas/1'],
]);

test('las zonas responden 401 sin token', function (string $metodo, string $ruta) {
    $this->json($metodo, $ruta)
        ->assertUnauthorized()
        ->assertJsonPath('error.codigo', 'NO_AUTENTICADO');
})->with('rutas de zonas');

test('solo el administrador puede administrar zonas', function (string $metodo, string $ruta) {
    foreach ([Rol::Operador, Rol::Comercio, Rol::Repartidor] as $rol) {
        Sanctum::actingAs(Usuario::factory()->{$rol->value}()->create());

        $this->json($metodo, $ruta, ['nombre' => 'Intrusa'])
            ->assertForbidden()
            ->assertJsonPath('error.codigo', 'NO_AUTORIZADO');
    }

    expect(Zona::query()->where('nombre', 'Intrusa')->exists())->toBeFalse();
})->with('rutas de zonas');
