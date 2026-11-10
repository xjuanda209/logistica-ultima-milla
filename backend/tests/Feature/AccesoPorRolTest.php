<?php

use App\Modules\Administracion\Models\Usuario;
use Laravel\Sanctum\Sanctum;

function usuarioConRol(string $rol): Usuario
{
    return Usuario::factory()->{$rol}()->create();
}

test('el repartidor no puede consultar clientes ni el panel', function (string $ruta) {
    Sanctum::actingAs(usuarioConRol('repartidor'));

    $this->getJson($ruta)
        ->assertForbidden()
        ->assertJsonPath('error.codigo', 'NO_AUTORIZADO');
})->with(['/api/clientes', '/api/panel/resumen']);

test('administrador, operador y comercio pueden consultar clientes y el panel', function (string $rol) {
    Sanctum::actingAs(usuarioConRol($rol));

    $this->getJson('/api/clientes')->assertOk();
    $this->getJson('/api/panel/resumen')->assertOk();
})->with(['administrador', 'operador', 'comercio']);

test('todos los roles pueden consultar pedidos e incidencias', function (string $rol) {
    Sanctum::actingAs(usuarioConRol($rol));

    $this->getJson('/api/pedidos')->assertOk();
    $this->getJson('/api/incidencias')->assertOk();
})->with(['administrador', 'operador', 'comercio', 'repartidor']);

test('una ruta inexistente responde 404 con el formato de error', function () {
    Sanctum::actingAs(usuarioConRol('administrador'));

    $this->getJson('/api/no-existe')
        ->assertNotFound()
        ->assertJsonPath('error.codigo', 'NO_ENCONTRADO');
});
