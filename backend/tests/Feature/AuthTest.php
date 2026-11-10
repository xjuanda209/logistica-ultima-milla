<?php

use App\Modules\Administracion\Models\Usuario;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;

test('un usuario inicia sesión con correo y contraseña y recibe un token', function () {
    $usuario = Usuario::factory()->operador()->create(['correo' => 'operador@prueba.test']);

    $respuesta = $this->postJson('/api/login', [
        'correo' => 'operador@prueba.test',
        'password' => 'password',
    ]);

    $respuesta->assertOk()
        ->assertJsonPath('usuario.id', $usuario->id)
        ->assertJsonPath('usuario.rol', 'operador')
        ->assertJsonMissingPath('usuario.password');

    expect($respuesta->json('token'))->toBeString()->not->toBeEmpty();
});

test('el login rechaza una contraseña incorrecta con el formato de error del proyecto', function () {
    Usuario::factory()->create(['correo' => 'operador@prueba.test']);

    $this->postJson('/api/login', ['correo' => 'operador@prueba.test', 'password' => 'incorrecta'])
        ->assertUnauthorized()
        ->assertJsonPath('error.codigo', 'CREDENCIALES_INVALIDAS')
        ->assertJsonStructure(['error' => ['codigo', 'mensaje', 'campos']]);
});

test('el login rechaza a un usuario inactivo', function () {
    Usuario::factory()->inactivo()->create(['correo' => 'inactivo@prueba.test']);

    $this->postJson('/api/login', ['correo' => 'inactivo@prueba.test', 'password' => 'password'])
        ->assertForbidden()
        ->assertJsonPath('error.codigo', 'USUARIO_INACTIVO');
});

test('el login valida los campos obligatorios e indica cuáles fallaron', function () {
    $this->postJson('/api/login', ['correo' => 'no-es-un-correo'])
        ->assertUnprocessable()
        ->assertJsonPath('error.codigo', 'VALIDACION')
        ->assertJsonStructure(['error' => ['campos' => ['correo', 'password']]]);
});

test('me devuelve los datos del usuario autenticado', function () {
    $usuario = Usuario::factory()->comercio()->create();
    Sanctum::actingAs($usuario);

    $this->getJson('/api/me')
        ->assertOk()
        ->assertJsonPath('data.correo', $usuario->correo)
        ->assertJsonPath('data.rol', 'comercio')
        ->assertJsonPath('data.comercio.id', $usuario->comercio_id);
});

test('las rutas protegidas responden 401 sin token', function (string $ruta) {
    $this->getJson($ruta)
        ->assertUnauthorized()
        ->assertJsonPath('error.codigo', 'NO_AUTENTICADO');
})->with(['/api/me', '/api/pedidos', '/api/clientes', '/api/incidencias', '/api/panel/resumen']);

test('logout elimina el token usado', function () {
    Usuario::factory()->create(['correo' => 'operador@prueba.test']);

    $token = $this->postJson('/api/login', ['correo' => 'operador@prueba.test', 'password' => 'password'])
        ->json('token');

    $this->withToken($token)->postJson('/api/logout')->assertOk();

    expect(PersonalAccessToken::query()->count())->toBe(0);
});
