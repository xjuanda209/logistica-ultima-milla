<?php

use App\Modules\Administracion\Models\Comercio;
use App\Modules\Administracion\Models\Usuario;
use App\Modules\Administracion\Models\Zona;
use App\Modules\Pedidos\Models\Cliente;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->comercio = Comercio::factory()->create(['nombre' => 'Panadería La Espiga']);
    $this->zona = Zona::factory()->create(['nombre' => 'Centro']);
    $this->operador = Usuario::factory()->operador()->create();
});

/**
 * Datos válidos de un cliente; lo que se pase reemplaza al valor por defecto.
 *
 * @param  array<string, mixed>  $cambios
 * @return array<string, mixed>
 */
function datosCliente(array $cambios = []): array
{
    return [
        'comercio_id' => test()->comercio->id,
        'nombre' => 'Laura Gómez',
        'cedula' => '1088200111',
        'telefono' => '3100000001',
        'correo' => 'laura@correo.test',
        'direccion' => 'Cra 8 # 20-15, apto 301',
        'barrio' => 'Centro',
        'indicaciones' => 'Portería, torre B',
        'zona_id' => test()->zona->id,
        ...$cambios,
    ];
}

test('la lista de clientes viene paginada, ordenada por nombre y con comercio y zona', function () {
    Sanctum::actingAs($this->operador);
    Cliente::factory()->create(['nombre' => 'Pedro Ruiz', 'comercio_id' => $this->comercio->id, 'zona_id' => $this->zona->id]);
    Cliente::factory()->create(['nombre' => 'Laura Gómez']);

    $this->getJson('/api/clientes')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.nombre', 'Laura Gómez')
        ->assertJsonPath('data.1.comercio.nombre', 'Panadería La Espiga')
        ->assertJsonPath('data.1.zona.nombre', 'Centro')
        ->assertJsonStructure([
            'data' => [['id', 'comercio_id', 'nombre', 'cedula', 'telefono', 'correo', 'direccion', 'barrio', 'indicaciones', 'zona_id']],
            'meta',
        ]);
});

test('los clientes se buscan por nombre, cédula o teléfono sin distinguir mayúsculas', function (string $texto) {
    Sanctum::actingAs($this->operador);
    $laura = Cliente::factory()->create(['nombre' => 'Laura Gómez', 'cedula' => '1088200111', 'telefono' => '3100000001']);
    Cliente::factory()->create(['nombre' => 'Pedro Ruiz', 'cedula' => '1077300222', 'telefono' => '3209999992']);

    $this->getJson('/api/clientes?buscar='.urlencode($texto))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $laura->id);
})->with(['nombre' => 'laura', 'cédula' => '8820011', 'teléfono' => '310000']);

test('administrador y operador filtran los clientes por comercio', function () {
    Sanctum::actingAs($this->operador);
    Cliente::factory()->count(2)->create(['comercio_id' => $this->comercio->id]);
    Cliente::factory()->count(3)->create();

    $this->getJson("/api/clientes?comercio_id={$this->comercio->id}")->assertOk()->assertJsonCount(2, 'data');
    $this->getJson('/api/clientes')->assertOk()->assertJsonCount(5, 'data');
});

test('la lista de clientes acepta por_pagina entre 1 y 100', function () {
    Sanctum::actingAs($this->operador);
    Cliente::factory()->count(25)->create(['comercio_id' => $this->comercio->id, 'zona_id' => $this->zona->id]);

    $this->getJson('/api/clientes')->assertOk()->assertJsonCount(20, 'data');
    $this->getJson('/api/clientes?por_pagina=100')->assertOk()->assertJsonCount(25, 'data');
    $this->getJson('/api/clientes?por_pagina=101')
        ->assertUnprocessable()
        ->assertJsonPath('error.codigo', 'VALIDACION');
});

test('un comercio no ve clientes ajenos aunque filtre por otro comercio o los busque', function () {
    $ajeno = Cliente::factory()->create(['nombre' => 'Cliente Ajeno']);
    Cliente::factory()->create(['comercio_id' => $this->comercio->id, 'nombre' => 'Cliente Propio']);
    Sanctum::actingAs(Usuario::factory()->comercio($this->comercio)->create());

    $this->getJson("/api/clientes?comercio_id={$ajeno->comercio_id}")->assertOk()->assertJsonCount(0, 'data');
    $this->getJson('/api/clientes?buscar=Ajeno')->assertOk()->assertJsonCount(0, 'data');
    $this->getJson('/api/clientes?buscar=Cliente')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.nombre', 'Cliente Propio');
});

test('administrador y operador crean un cliente en el comercio que eligen', function (string $rol) {
    Sanctum::actingAs(Usuario::factory()->{$rol}()->create());

    $this->postJson('/api/clientes', datosCliente())
        ->assertCreated()
        ->assertJsonPath('data.nombre', 'Laura Gómez')
        ->assertJsonPath('data.comercio.nombre', 'Panadería La Espiga')
        ->assertJsonPath('data.zona.nombre', 'Centro');

    $this->assertDatabaseHas('clientes', [
        'comercio_id' => $this->comercio->id,
        'cedula' => '1088200111',
        'direccion' => 'Cra 8 # 20-15, apto 301',
        'barrio' => 'Centro',
        'indicaciones' => 'Portería, torre B',
        'zona_id' => $this->zona->id,
    ]);
})->with(['administrador', 'operador']);

test('al comercio se le fuerza su propio comercio aunque la petición diga otro', function () {
    $otroComercio = Comercio::factory()->create();
    Sanctum::actingAs(Usuario::factory()->comercio($this->comercio)->create());

    $this->postJson('/api/clientes', datosCliente(['comercio_id' => $otroComercio->id]))
        ->assertCreated()
        ->assertJsonPath('data.comercio_id', $this->comercio->id);

    expect(Cliente::query()->where('comercio_id', $otroComercio->id)->exists())->toBeFalse();
});

test('el correo y las indicaciones del cliente son opcionales', function () {
    Sanctum::actingAs($this->operador);

    $this->postJson('/api/clientes', datosCliente(['correo' => '', 'indicaciones' => null]))
        ->assertCreated()
        ->assertJsonPath('data.correo', null)
        ->assertJsonPath('data.indicaciones', null);
});

test('crear un cliente exige sus datos obligatorios', function () {
    Sanctum::actingAs($this->operador);

    $respuesta = $this->postJson('/api/clientes', [])
        ->assertUnprocessable()
        ->assertJsonPath('error.codigo', 'VALIDACION')
        ->assertJsonPath('error.campos.nombre.0', 'El campo nombre es obligatorio.')
        ->assertJsonPath('error.campos.cedula.0', 'El campo cédula es obligatorio.')
        ->assertJsonPath('error.campos.telefono.0', 'El campo teléfono es obligatorio.')
        ->assertJsonPath('error.campos.direccion.0', 'El campo dirección es obligatorio.')
        ->assertJsonPath('error.campos.barrio.0', 'El campo barrio es obligatorio.')
        ->assertJsonPath('error.campos.zona_id.0', 'Elige la zona de la dirección.')
        ->assertJsonPath('error.campos.comercio_id.0', 'Elige el comercio del cliente.');

    expect($respuesta->json('error.campos'))->not->toHaveKey('correo')
        ->and(Cliente::query()->count())->toBe(0);
});

test('crear un cliente valida formatos y límites', function (array $cambios, string $campo, string $mensaje) {
    Sanctum::actingAs($this->operador);

    $this->postJson('/api/clientes', datosCliente($cambios))
        ->assertUnprocessable()
        ->assertJsonPath("error.campos.{$campo}.0", $mensaje);

    expect(Cliente::query()->count())->toBe(0);
})->with([
    'cédula con letras' => [['cedula' => '10AB200111'], 'cedula', 'La cédula debe tener entre 5 y 15 números.'],
    'cédula muy larga' => [['cedula' => str_repeat('1', 16)], 'cedula', 'La cédula debe tener entre 5 y 15 números.'],
    'teléfono con letras' => [['telefono' => 'no tengo'], 'telefono', 'El teléfono debe tener entre 7 y 15 números.'],
    'correo inválido' => [['correo' => 'no-es-correo'], 'correo', 'El campo correo debe ser un correo válido.'],
    'nombre muy largo' => [['nombre' => str_repeat('a', 121)], 'nombre', 'El campo nombre no puede tener más de 120 caracteres.'],
    'zona inexistente' => [['zona_id' => 9999], 'zona_id', 'El valor elegido para zona no existe.'],
    'comercio inexistente' => [['comercio_id' => 9999], 'comercio_id', 'El valor elegido para comercio no existe.'],
]);

test('la cédula es única dentro de cada comercio, también si se escribe con puntos', function (string $cedula) {
    Sanctum::actingAs($this->operador);
    Cliente::factory()->create(['comercio_id' => $this->comercio->id, 'cedula' => '1088200111']);

    $this->postJson('/api/clientes', datosCliente(['cedula' => $cedula]))
        ->assertUnprocessable()
        ->assertJsonPath('error.codigo', 'VALIDACION')
        ->assertJsonPath('error.campos.cedula.0', 'Ya existe un cliente con esa cédula en este comercio.');

    expect(Cliente::query()->count())->toBe(1);
})->with(['1088200111', '1.088.200.111']);

test('la misma cédula se puede registrar en otro comercio', function () {
    Sanctum::actingAs($this->operador);
    Cliente::factory()->create(['cedula' => '1088200111']);

    $this->postJson('/api/clientes', datosCliente(['cedula' => '1.088.200.111']))
        ->assertCreated()
        ->assertJsonPath('data.cedula', '1088200111');
});

test('se edita un cliente y puede conservar su cédula', function () {
    Sanctum::actingAs($this->operador);
    $otraZona = Zona::factory()->create();
    $cliente = Cliente::factory()->create(['comercio_id' => $this->comercio->id, 'cedula' => '1088200111']);

    $this->putJson("/api/clientes/{$cliente->id}", datosCliente([
        'nombre' => 'Laura Gómez Ríos', 'direccion' => 'Calle 14 # 5-40', 'barrio' => 'Cuba', 'zona_id' => $otraZona->id,
    ]))
        ->assertOk()
        ->assertJsonPath('data.nombre', 'Laura Gómez Ríos')
        ->assertJsonPath('data.cedula', '1088200111')
        ->assertJsonPath('data.zona.id', $otraZona->id);

    expect($cliente->fresh()->direccion)->toBe('Calle 14 # 5-40');
});

test('editar un cliente no permite usar la cédula de otro cliente del mismo comercio', function () {
    Sanctum::actingAs($this->operador);
    Cliente::factory()->create(['comercio_id' => $this->comercio->id, 'cedula' => '1088200222']);
    $cliente = Cliente::factory()->create(['comercio_id' => $this->comercio->id, 'cedula' => '1088200111']);

    $this->putJson("/api/clientes/{$cliente->id}", datosCliente(['cedula' => '1088200222']))
        ->assertUnprocessable()
        ->assertJsonPath('error.campos.cedula.0', 'Ya existe un cliente con esa cédula en este comercio.');

    expect($cliente->fresh()->cedula)->toBe('1088200111');
});

test('al editar un cliente su comercio no cambia', function () {
    Sanctum::actingAs($this->operador);
    $otroComercio = Comercio::factory()->create();
    $cliente = Cliente::factory()->create(['comercio_id' => $this->comercio->id]);

    $this->putJson("/api/clientes/{$cliente->id}", datosCliente(['comercio_id' => $otroComercio->id]))
        ->assertOk()
        ->assertJsonPath('data.comercio_id', $this->comercio->id);
});

test('editar un cliente que no existe responde 404', function () {
    Sanctum::actingAs($this->operador);

    $this->putJson('/api/clientes/9999', datosCliente())
        ->assertNotFound()
        ->assertJsonPath('error.codigo', 'NO_ENCONTRADO');
});

test('un comercio no puede editar un cliente de otro comercio', function () {
    $ajeno = Cliente::factory()->create(['nombre' => 'Cliente Ajeno']);
    Sanctum::actingAs(Usuario::factory()->comercio($this->comercio)->create());

    $this->putJson("/api/clientes/{$ajeno->id}", datosCliente(['nombre' => 'Intruso']))
        ->assertNotFound()
        ->assertJsonPath('error.codigo', 'NO_ENCONTRADO');

    expect($ajeno->fresh()->nombre)->toBe('Cliente Ajeno');
});

dataset('rutas de clientes', [
    'listar' => ['GET', '/api/clientes'],
    'crear' => ['POST', '/api/clientes'],
    'editar' => ['PUT', '/api/clientes/{id}'],
]);

test('los clientes responden 401 sin token', function (string $metodo, string $ruta) {
    $this->json($metodo, str_replace('{id}', '1', $ruta))
        ->assertUnauthorized()
        ->assertJsonPath('error.codigo', 'NO_AUTENTICADO');
})->with('rutas de clientes');

test('el repartidor no puede consultar, crear ni editar clientes', function (string $metodo, string $ruta) {
    $cliente = Cliente::factory()->create(['nombre' => 'Original']);
    Sanctum::actingAs(Usuario::factory()->repartidor()->create());

    $this->json($metodo, str_replace('{id}', (string) $cliente->id, $ruta), datosCliente(['nombre' => 'Intruso']))
        ->assertForbidden()
        ->assertJsonPath('error.codigo', 'NO_AUTORIZADO');

    expect(Cliente::query()->count())->toBe(1)
        ->and($cliente->fresh()->nombre)->toBe('Original');
})->with('rutas de clientes');
