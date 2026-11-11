<?php

use App\Modules\Administracion\Models\Comercio;
use App\Modules\Administracion\Models\Usuario;
use App\Modules\Administracion\Models\Zona;
use App\Shared\Enums\Disponibilidad;
use App\Shared\Enums\Rol;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

function comoAdministrador(): Usuario
{
    $administrador = Usuario::factory()->administrador()->create();
    Sanctum::actingAs($administrador);

    return $administrador;
}

/**
 * @param  array<string, mixed>  $cambios
 * @return array<string, mixed>
 */
function datosDeUsuario(array $cambios = []): array
{
    return [
        'nombre' => 'Olga Operadora',
        'correo' => 'olga@prueba.test',
        'password' => 'secreta123',
        'rol' => 'operador',
        ...$cambios,
    ];
}

// ---------- Listar ----------

test('el administrador lista los usuarios paginados con su comercio y su zona', function () {
    comoAdministrador();
    $comercio = Usuario::factory()->comercio()->create();
    $repartidor = Usuario::factory()->repartidor()->create();

    $respuesta = $this->getJson('/api/usuarios')
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonStructure(['data' => [['id', 'nombre', 'correo', 'rol', 'activo', 'comercio', 'zona', 'created_at', 'updated_at']], 'meta'])
        ->assertJsonMissingPath('data.0.password');

    $porId = collect($respuesta->json('data'))->keyBy('id');

    expect($porId[$comercio->id]['comercio']['id'])->toBe($comercio->comercio_id)
        ->and($porId[$repartidor->id]['zona']['id'])->toBe($repartidor->zona_id);
});

// ---------- Crear ----------

test('el administrador crea un operador activo y este puede iniciar sesión con la contraseña inicial', function () {
    comoAdministrador();

    $this->postJson('/api/usuarios', datosDeUsuario(['correo' => 'Olga@Prueba.test']))
        ->assertCreated()
        ->assertJsonPath('data.correo', 'olga@prueba.test')
        ->assertJsonPath('data.rol', 'operador')
        ->assertJsonPath('data.activo', true)
        ->assertJsonPath('data.comercio_id', null)
        ->assertJsonPath('data.zona_id', null)
        ->assertJsonPath('data.disponibilidad', null)
        ->assertJsonMissingPath('data.password');

    $this->postJson('/api/login', ['correo' => 'olga@prueba.test', 'password' => 'secreta123'])->assertOk();
});

test('al crear un usuario comercio el comercio es obligatorio y queda guardado', function () {
    comoAdministrador();
    $comercio = Comercio::factory()->create();

    $this->postJson('/api/usuarios', datosDeUsuario(['rol' => 'comercio']))
        ->assertUnprocessable()
        ->assertJsonPath('error.codigo', 'VALIDACION')
        ->assertJsonPath('error.campos.comercio_id.0', 'Elige el comercio al que pertenece el usuario.');

    $this->postJson('/api/usuarios', datosDeUsuario(['rol' => 'comercio', 'comercio_id' => 9999]))
        ->assertUnprocessable()
        ->assertJsonStructure(['error' => ['campos' => ['comercio_id']]]);

    $this->postJson('/api/usuarios', datosDeUsuario(['rol' => 'comercio', 'comercio_id' => $comercio->id]))
        ->assertCreated()
        ->assertJsonPath('data.comercio.id', $comercio->id)
        ->assertJsonPath('data.zona_id', null);
});

test('al crear un repartidor la zona es obligatoria y empieza disponible', function () {
    comoAdministrador();
    $zona = Zona::factory()->create();

    $this->postJson('/api/usuarios', datosDeUsuario(['rol' => 'repartidor']))
        ->assertUnprocessable()
        ->assertJsonPath('error.campos.zona_id.0', 'Elige la zona que cubre el repartidor.');

    $this->postJson('/api/usuarios', datosDeUsuario(['rol' => 'repartidor', 'zona_id' => $zona->id]))
        ->assertCreated()
        ->assertJsonPath('data.zona.id', $zona->id)
        ->assertJsonPath('data.disponibilidad', 'disponible')
        ->assertJsonPath('data.comercio_id', null);
});

test('un rol que no usa comercio ni zona los ignora aunque se envíen', function () {
    comoAdministrador();

    $this->postJson('/api/usuarios', datosDeUsuario([
        'comercio_id' => Comercio::factory()->create()->id,
        'zona_id' => Zona::factory()->create()->id,
    ]))
        ->assertCreated()
        ->assertJsonPath('data.comercio_id', null)
        ->assertJsonPath('data.zona_id', null);
});

test('crear un usuario valida los campos y responde los mensajes en español', function () {
    comoAdministrador();

    $this->postJson('/api/usuarios', ['correo' => 'no-es-correo', 'password' => 'corta', 'rol' => 'jefe'])
        ->assertUnprocessable()
        ->assertJsonPath('error.codigo', 'VALIDACION')
        ->assertJsonPath('error.campos.nombre.0', 'El campo nombre es obligatorio.')
        ->assertJsonPath('error.campos.correo.0', 'El campo correo debe ser un correo válido.')
        ->assertJsonPath('error.campos.password.0', 'El campo contraseña debe tener al menos 8 caracteres.')
        ->assertJsonPath('error.campos.rol.0', 'El valor elegido para rol no es válido.');

    expect(Usuario::query()->count())->toBe(1);
});

test('el correo es único sin importar mayúsculas', function () {
    comoAdministrador();
    Usuario::factory()->create(['correo' => 'olga@prueba.test']);

    $this->postJson('/api/usuarios', datosDeUsuario(['correo' => 'OLGA@prueba.test']))
        ->assertUnprocessable()
        ->assertJsonPath('error.campos.correo.0', 'El correo ya existe.');
});

// ---------- Editar ----------

test('el administrador edita un usuario y puede conservar su mismo correo', function () {
    comoAdministrador();
    $usuario = Usuario::factory()->operador()->create(['correo' => 'olga@prueba.test']);

    $this->putJson("/api/usuarios/{$usuario->id}", datosDeUsuario(['nombre' => 'Olga Editada']))
        ->assertOk()
        ->assertJsonPath('data.nombre', 'Olga Editada')
        ->assertJsonPath('data.correo', 'olga@prueba.test');
});

test('editar no permite usar el correo de otro usuario', function () {
    comoAdministrador();
    Usuario::factory()->create(['correo' => 'ocupado@prueba.test']);
    $usuario = Usuario::factory()->create();

    $this->putJson("/api/usuarios/{$usuario->id}", datosDeUsuario(['correo' => 'ocupado@prueba.test']))
        ->assertUnprocessable()
        ->assertJsonStructure(['error' => ['campos' => ['correo']]]);
});

test('editar no cambia la contraseña aunque se envíe', function () {
    comoAdministrador();
    $usuario = Usuario::factory()->create();

    $this->putJson("/api/usuarios/{$usuario->id}", datosDeUsuario(['password' => 'otra-clave-123']))->assertOk();

    expect(Hash::check('password', $usuario->fresh()->password))->toBeTrue();
});

test('al cambiar el rol se ajustan comercio, zona y disponibilidad', function () {
    comoAdministrador();
    $zona = Zona::factory()->create();
    $usuario = Usuario::factory()->comercio()->create();

    $this->putJson("/api/usuarios/{$usuario->id}", datosDeUsuario(['rol' => 'repartidor']))
        ->assertUnprocessable()
        ->assertJsonStructure(['error' => ['campos' => ['zona_id']]]);

    $this->putJson("/api/usuarios/{$usuario->id}", datosDeUsuario(['rol' => 'repartidor', 'zona_id' => $zona->id]))
        ->assertOk()
        ->assertJsonPath('data.comercio_id', null)
        ->assertJsonPath('data.zona_id', $zona->id)
        ->assertJsonPath('data.disponibilidad', 'disponible');

    $this->putJson("/api/usuarios/{$usuario->id}", datosDeUsuario(['rol' => 'operador']))
        ->assertOk()
        ->assertJsonPath('data.zona_id', null)
        ->assertJsonPath('data.disponibilidad', null);
});

test('editar un repartidor conserva la disponibilidad que tenía', function () {
    comoAdministrador();
    $repartidor = Usuario::factory()->repartidor()->create(['disponibilidad' => Disponibilidad::NoDisponible]);

    $this->putJson("/api/usuarios/{$repartidor->id}", datosDeUsuario([
        'rol' => 'repartidor',
        'zona_id' => $repartidor->zona_id,
    ]))
        ->assertOk()
        ->assertJsonPath('data.disponibilidad', 'no_disponible');
});

test('editar un usuario que no existe responde 404', function () {
    comoAdministrador();

    $this->putJson('/api/usuarios/9999', datosDeUsuario())
        ->assertNotFound()
        ->assertJsonPath('error.codigo', 'NO_ENCONTRADO');
});

// ---------- Activar / desactivar ----------

test('el administrador desactiva y reactiva a otro usuario', function () {
    comoAdministrador();
    $usuario = Usuario::factory()->create();

    $this->putJson("/api/usuarios/{$usuario->id}/estado", ['activo' => false])
        ->assertOk()
        ->assertJsonPath('data.activo', false);

    $this->putJson("/api/usuarios/{$usuario->id}/estado", ['activo' => true])
        ->assertOk()
        ->assertJsonPath('data.activo', true);
});

test('un administrador no puede desactivarse a sí mismo', function () {
    $administrador = comoAdministrador();

    $this->putJson("/api/usuarios/{$administrador->id}/estado", ['activo' => false])
        ->assertConflict()
        ->assertJsonPath('error.codigo', 'NO_PUEDES_DESACTIVARTE')
        ->assertJsonPath(
            'error.mensaje',
            'No puedes desactivar tu propia cuenta: el sistema debe conservar al menos un administrador activo.',
        )
        ->assertJsonStructure(['error' => ['codigo', 'mensaje', 'campos']]);

    expect($administrador->fresh()->activo)->toBeTrue();
});

test('un administrador no puede cambiar su propio rol pero sí sus demás datos', function () {
    $administrador = comoAdministrador();
    Usuario::factory()->administrador()->create();

    $this->putJson("/api/usuarios/{$administrador->id}", datosDeUsuario(['rol' => 'operador']))
        ->assertConflict()
        ->assertJsonPath('error.codigo', 'NO_PUEDES_CAMBIAR_TU_ROL')
        ->assertJsonStructure(['error' => ['codigo', 'mensaje', 'campos']]);

    expect($administrador->fresh()->rol)->toBe(Rol::Administrador);

    $this->putJson("/api/usuarios/{$administrador->id}", datosDeUsuario(['rol' => 'administrador', 'nombre' => 'Ana Editada']))
        ->assertOk()
        ->assertJsonPath('data.nombre', 'Ana Editada')
        ->assertJsonPath('data.rol', 'administrador');
});

test('un administrador puede desactivar o cambiar el rol de otro administrador si queda alguno activo', function () {
    comoAdministrador();
    $otro = Usuario::factory()->administrador()->create();
    $tercero = Usuario::factory()->administrador()->create();

    $this->putJson("/api/usuarios/{$otro->id}/estado", ['activo' => false])->assertOk();

    $this->putJson("/api/usuarios/{$tercero->id}", datosDeUsuario(['rol' => 'operador']))
        ->assertOk()
        ->assertJsonPath('data.rol', 'operador');
});

/*
| Por la API el último administrador activo siempre es quien hace la petición (y ya no puede
| desactivarse ni cambiar su rol). Estas pruebas cubren la regla por sí sola simulando el caso
| límite: quien pide ya fue desactivado en otra sesión y solo queda un administrador activo.
*/
test('no se puede desactivar al último administrador activo', function () {
    Sanctum::actingAs(Usuario::factory()->administrador()->inactivo()->create());
    $ultimo = Usuario::factory()->administrador()->create();

    $this->putJson("/api/usuarios/{$ultimo->id}/estado", ['activo' => false])
        ->assertConflict()
        ->assertJsonPath('error.codigo', 'ULTIMO_ADMINISTRADOR')
        ->assertJsonStructure(['error' => ['codigo', 'mensaje', 'campos']]);

    expect($ultimo->fresh()->activo)->toBeTrue()
        ->and($ultimo->tokens()->count())->toBe(0);
});

test('no se puede cambiar el rol del último administrador activo', function () {
    Sanctum::actingAs(Usuario::factory()->administrador()->inactivo()->create());
    $ultimo = Usuario::factory()->administrador()->create();

    $this->putJson("/api/usuarios/{$ultimo->id}", datosDeUsuario(['rol' => 'operador']))
        ->assertConflict()
        ->assertJsonPath('error.codigo', 'ULTIMO_ADMINISTRADOR');

    expect($ultimo->fresh()->rol)->toBe(Rol::Administrador);

    $this->putJson("/api/usuarios/{$ultimo->id}", datosDeUsuario(['rol' => 'administrador', 'nombre' => 'Ana Editada']))
        ->assertOk();
});

test('un administrador inactivo no cuenta como el último: se le puede cambiar el rol', function () {
    comoAdministrador();
    $inactivo = Usuario::factory()->administrador()->inactivo()->create();

    $this->putJson("/api/usuarios/{$inactivo->id}", datosDeUsuario(['rol' => 'operador']))
        ->assertOk()
        ->assertJsonPath('data.rol', 'operador');
});

test('cambiar el estado exige el campo activo', function () {
    comoAdministrador();
    $usuario = Usuario::factory()->create();

    $this->putJson("/api/usuarios/{$usuario->id}/estado", [])
        ->assertUnprocessable()
        ->assertJsonStructure(['error' => ['campos' => ['activo']]]);
});

test('un usuario desactivado no puede iniciar sesión y sus tokens dejan de servir', function () {
    $administrador = Usuario::factory()->administrador()->create();
    $usuario = Usuario::factory()->operador()->create(['correo' => 'olga@prueba.test']);
    $credenciales = ['correo' => 'olga@prueba.test', 'password' => 'password'];

    $token = $this->postJson('/api/login', $credenciales)->json('token');
    $this->withToken($token)->getJson('/api/me')->assertOk();

    // forgetGuards() hace que cada petición vuelva a autenticarse, como en la vida real.
    $this->app['auth']->forgetGuards();
    Sanctum::actingAs($administrador);
    $this->putJson("/api/usuarios/{$usuario->id}/estado", ['activo' => false])->assertOk();

    $this->app['auth']->forgetGuards();
    $this->withToken($token)->getJson('/api/me')
        ->assertUnauthorized()
        ->assertJsonPath('error.codigo', 'NO_AUTENTICADO');

    $this->postJson('/api/login', $credenciales)
        ->assertUnauthorized()
        ->assertJsonPath('error.codigo', 'CREDENCIALES_INVALIDAS')
        ->assertJsonPath('error.mensaje', 'Correo o contraseña incorrectos.');

    expect($usuario->tokens()->count())->toBe(0);
});

// ---------- Restablecer contraseña ----------

test('el administrador restablece la contraseña y se cierran las sesiones de ese usuario', function () {
    comoAdministrador();
    $usuario = Usuario::factory()->create(['correo' => 'olga@prueba.test']);
    $usuario->createToken('api');

    $this->putJson("/api/usuarios/{$usuario->id}/password", ['password' => 'nueva-clave-123'])
        ->assertOk()
        ->assertJsonMissingPath('data.password');

    expect($usuario->tokens()->count())->toBe(0);

    $this->postJson('/api/login', ['correo' => 'olga@prueba.test', 'password' => 'password'])
        ->assertUnauthorized()
        ->assertJsonPath('error.codigo', 'CREDENCIALES_INVALIDAS');

    $this->postJson('/api/login', ['correo' => 'olga@prueba.test', 'password' => 'nueva-clave-123'])->assertOk();
});

test('restablecer la contraseña exige al menos 8 caracteres', function () {
    comoAdministrador();
    $usuario = Usuario::factory()->create();

    $this->putJson("/api/usuarios/{$usuario->id}/password", ['password' => 'corta'])
        ->assertUnprocessable()
        ->assertJsonPath('error.campos.password.0', 'El campo contraseña debe tener al menos 8 caracteres.');

    expect(Hash::check('password', $usuario->fresh()->password))->toBeTrue();
});

// ---------- Permisos ----------

dataset('rutas de usuarios', [
    'listar' => ['GET', '/api/usuarios'],
    'crear' => ['POST', '/api/usuarios'],
    'editar' => ['PUT', '/api/usuarios/1'],
    'cambiar estado' => ['PUT', '/api/usuarios/1/estado'],
    'restablecer contraseña' => ['PUT', '/api/usuarios/1/password'],
]);

test('la administración de usuarios responde 401 sin token', function (string $metodo, string $ruta) {
    $this->json($metodo, $ruta)
        ->assertUnauthorized()
        ->assertJsonPath('error.codigo', 'NO_AUTENTICADO');
})->with('rutas de usuarios');

test('solo el administrador puede administrar usuarios', function (string $metodo, string $ruta) {
    foreach ([Rol::Operador, Rol::Comercio, Rol::Repartidor] as $rol) {
        Sanctum::actingAs(Usuario::factory()->{$rol->value}()->create());

        $this->json($metodo, $ruta, datosDeUsuario())
            ->assertForbidden()
            ->assertJsonPath('error.codigo', 'NO_AUTORIZADO');
    }

    expect(Usuario::query()->where('correo', 'olga@prueba.test')->exists())->toBeFalse();
})->with('rutas de usuarios');

// ---------- Comercios (catálogo de solo lectura) ----------

test('el administrador lista los comercios y puede pedir hasta 100 por página', function () {
    comoAdministrador();
    Comercio::factory()->count(25)->create();

    $this->getJson('/api/comercios')->assertOk()->assertJsonCount(20, 'data');

    $this->getJson('/api/comercios?por_pagina=100')
        ->assertOk()
        ->assertJsonCount(25, 'data')
        ->assertJsonStructure(['data' => [['id', 'nombre', 'direccion_recogida', 'activo']]]);

    $this->getJson('/api/comercios?por_pagina=500')
        ->assertUnprocessable()
        ->assertJsonStructure(['error' => ['campos' => ['por_pagina']]]);
});

test('la lista de comercios responde 401 sin token', function () {
    $this->getJson('/api/comercios')
        ->assertUnauthorized()
        ->assertJsonPath('error.codigo', 'NO_AUTENTICADO');
});

test('administrador, operador y repartidor ven todos los comercios', function (string $rol) {
    Comercio::factory()->count(3)->create();
    Sanctum::actingAs(Usuario::factory()->{$rol}()->create());

    $this->getJson('/api/comercios')->assertOk()->assertJsonCount(3, 'data');
})->with(['administrador', 'operador', 'repartidor']);

test('el usuario de un comercio solo ve su propio comercio', function () {
    $miComercio = Comercio::factory()->create();
    Comercio::factory()->count(3)->create();
    Sanctum::actingAs(Usuario::factory()->comercio($miComercio)->create());

    $this->getJson('/api/comercios?por_pagina=100')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $miComercio->id)
        ->assertJsonPath('meta.total', 1);
});

test('un usuario comercio sin comercio asignado no ve ninguno', function () {
    Comercio::factory()->count(2)->create();
    Sanctum::actingAs(Usuario::factory()->create(['rol' => Rol::Comercio, 'comercio_id' => null]));

    $this->getJson('/api/comercios')->assertOk()->assertJsonCount(0, 'data');
});
