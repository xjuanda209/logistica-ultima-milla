<?php

use App\Modules\Administracion\Models\Configuracion;
use App\Modules\Administracion\Models\Usuario;
use App\Shared\Enums\Rol;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->administrador = Usuario::factory()->administrador()->create();
});

test('el administrador ve la configuración con su valor por defecto', function () {
    Sanctum::actingAs($this->administrador);

    $this->getJson('/api/configuracion')
        ->assertOk()
        ->assertExactJson(['data' => ['max_pedidos_activos' => 3]]);
});

test('el administrador edita el máximo de pedidos activos', function (int $valor) {
    Sanctum::actingAs($this->administrador);

    $this->putJson('/api/configuracion', ['max_pedidos_activos' => $valor])
        ->assertOk()
        ->assertJsonPath('data.max_pedidos_activos', $valor);

    $this->getJson('/api/configuracion')->assertJsonPath('data.max_pedidos_activos', $valor);

    expect(Configuracion::query()->count())->toBe(1);
})->with([1, 8, 20]);

test('el máximo de pedidos activos debe ser un entero entre 1 y 20', function (mixed $valor, string $mensaje) {
    Sanctum::actingAs($this->administrador);
    Configuracion::actual()->update(['max_pedidos_activos' => 5]);

    $this->putJson('/api/configuracion', ['max_pedidos_activos' => $valor])
        ->assertUnprocessable()
        ->assertJsonPath('error.codigo', 'VALIDACION')
        ->assertJsonPath('error.campos.max_pedidos_activos.0', $mensaje);

    expect(Configuracion::actual()->max_pedidos_activos)->toBe(5);
})->with([
    'cero' => [0, 'El campo máximo de pedidos activos debe estar entre 1 y 20.'],
    'negativo' => [-1, 'El campo máximo de pedidos activos debe estar entre 1 y 20.'],
    'veintiuno' => [21, 'El campo máximo de pedidos activos debe estar entre 1 y 20.'],
    'decimal' => [2.5, 'El campo máximo de pedidos activos debe ser un número entero.'],
    'texto' => ['muchos', 'El campo máximo de pedidos activos debe ser un número entero.'],
    'vacío' => [null, 'El campo máximo de pedidos activos es obligatorio.'],
]);

dataset('rutas de configuración', [
    'ver' => ['GET', '/api/configuracion'],
    'editar' => ['PUT', '/api/configuracion'],
]);

test('la configuración responde 401 sin token', function (string $metodo, string $ruta) {
    $this->json($metodo, $ruta)
        ->assertUnauthorized()
        ->assertJsonPath('error.codigo', 'NO_AUTENTICADO');
})->with('rutas de configuración');

test('solo el administrador puede ver y editar la configuración', function (string $metodo, string $ruta) {
    foreach ([Rol::Operador, Rol::Comercio, Rol::Repartidor] as $rol) {
        Sanctum::actingAs(Usuario::factory()->{$rol->value}()->create());

        $this->json($metodo, $ruta, ['max_pedidos_activos' => 15])
            ->assertForbidden()
            ->assertJsonPath('error.codigo', 'NO_AUTORIZADO');
    }

    expect(Configuracion::actual()->max_pedidos_activos)->toBe(3);
})->with('rutas de configuración');
