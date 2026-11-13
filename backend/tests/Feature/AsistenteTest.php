<?php

use App\Modules\Administracion\Models\Usuario;
use App\Modules\Administracion\Models\Zona;
use App\Modules\Asistente\IA\ClienteGemini;
use App\Modules\Asistente\IA\ClienteGroq;
use App\Modules\Asistente\IA\ClienteIA;
use App\Modules\Asistente\IA\ContextoOperacion;
use App\Modules\Asistente\IA\IaNoDisponible;
use App\Modules\Asistente\Models\ConsultaAsistente;
use App\Modules\Incidencias\Models\Incidencia;
use App\Modules\Incidencias\Models\TipoIncidencia;
use App\Modules\Pedidos\Models\Cliente;
use App\Modules\Pedidos\Models\Pedido;
use App\Shared\Enums\EstadoIncidencia;
use App\Shared\Enums\ResultadoConsulta;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\Soporte\ClienteIASimulado;

beforeEach(function () {
    // Ninguna prueba debe salir a internet: si alguna llama a un proveedor real, falla.
    Http::preventStrayRequests();

    $this->operador = Usuario::factory()->operador()->create();
    Sanctum::actingAs($this->operador);
});

/**
 * Pedido PED-0131 con un cliente de datos reconocibles y una incidencia que los menciona.
 */
function pedidoConDatosPersonales(): Pedido
{
    $cliente = Cliente::factory()->create([
        'nombre' => 'Mariana Restrepo Gómez',
        'cedula' => '1088123456',
        'telefono' => '3105551234',
        'correo' => 'mariana.restrepo@correo.test',
        'direccion' => 'Calle 21 # 7-45 apto 302',
    ]);
    $pedido = Pedido::factory()->create([
        'numero' => 'PED-0131',
        'estado' => 'en_ruta',
        'cliente_id' => $cliente->id,
        'comercio_id' => $cliente->comercio_id,
        'zona_id' => Zona::factory()->create(['nombre' => 'Cuba'])->id,
        'direccion' => 'Calle 21 # 7-45 apto 302',
        'barrio' => 'Barrio Secreto',
        'indicaciones' => 'Portería azul',
        'observaciones' => 'Llamar antes al 3105551234',
    ]);
    $pedido->lineas()->create(['producto' => 'Torta de chocolate', 'cantidad' => 2]);

    Incidencia::factory()->for($pedido)->create([
        'tipo_incidencia_id' => TipoIncidencia::factory()->create(['nombre' => 'Cliente ausente'])->id,
        'descripcion' => 'Mariana Restrepo Gómez no abrió en la Calle 21 # 7-45 apto 302. La señora Restrepo '
            .'pidió llamarla al 310 555 1234 o escribirle a otra.persona@correo.test, cédula 1088123456.',
        'nota_comercio' => 'Confirmar con Mariana.',
    ]);

    return $pedido;
}

// ── Consultas (INT-14) ───────────────────────────────────────────────────────

test('el asistente responde con la respuesta de la IA y registra la consulta como respondida', function () {
    Pedido::factory()->create(['numero' => 'PED-0001', 'estado' => 'en_ruta']);
    $ia = ClienteIASimulado::instalar('Hay 1 pedido en ruta: PED-0001.');

    $this->postJson('/api/asistente/consultas', ['pregunta' => '¿Cuántos pedidos están en ruta?'])
        ->assertOk()
        ->assertExactJson(['data' => ['respuesta' => 'Hay 1 pedido en ruta: PED-0001.']]);

    $consulta = ConsultaAsistente::query()->sole();

    expect($consulta->usuario_id)->toBe($this->operador->id)
        ->and($consulta->resultado)->toBe(ResultadoConsulta::Respondida)
        ->and($ia->llamadas)->toBe(1)
        ->and($ia->mensajes)->toBe([['rol' => 'usuario', 'texto' => '¿Cuántos pedidos están en ruta?']])
        ->and($ia->instrucciones)->toContain('PED-0001')
        ->and($ia->instrucciones)->toContain('Pedidos por estado actual:')
        ->and($ia->instrucciones)->toContain('En ruta 1');
});

test('a la IA se le indica que solo informa y qué responder fuera de tema o sin datos', function () {
    Pedido::factory()->create();
    $ia = ClienteIASimulado::instalar(ContextoOperacion::MENSAJE_FUERA_DE_TEMA);

    $this->postJson('/api/asistente/consultas', ['pregunta' => '¿Quién ganó el mundial de 2014?'])
        ->assertOk()
        ->assertJsonPath('data.respuesta', 'Solo atiendo consultas sobre pedidos e incidencias de la operación.');

    expect($ia->instrucciones)
        ->toContain('no puedes crear, modificar ni eliminar nada')
        ->toContain('Solo atiendo consultas sobre pedidos e incidencias de la operación.')
        ->toContain('No se encontró información sobre esa consulta.');
});

test('los datos que recibe la IA van anonimizados: sin nombre, cédula, teléfono, correo ni dirección del cliente', function () {
    pedidoConDatosPersonales();
    $ia = ClienteIASimulado::instalar();

    $this->postJson('/api/asistente/consultas', ['pregunta' => 'Resume las incidencias del pedido PED-0131'])
        ->assertOk();

    expect($ia->instrucciones)
        ->toContain('PED-0131')
        ->toContain('Cliente ausente')
        ->toContain('Torta de chocolate × 2')
        ->toContain('zona: Cuba')
        ->not->toContain('Mariana')
        ->not->toContain('Restrepo')
        ->not->toContain('Gómez')
        ->not->toContain('1088123456')
        ->not->toContain('3105551234')
        ->not->toContain('310 555 1234')
        ->not->toContain('correo.test')
        ->not->toContain('Calle 21')
        ->not->toContain('apto 302')
        ->not->toContain('Barrio Secreto')
        ->not->toContain('Portería azul')
        ->not->toContain('Llamar antes');
});

test('anonimizar tapa los datos del cliente, correos y números largos, y respeta el número de pedido', function () {
    $cliente = Cliente::factory()->make(['nombre' => 'Ana María Ruiz', 'telefono' => '6063334455']);

    expect(ContextoOperacion::anonimizar('Ana María Ruiz (PED-0131) dejó el 606 333 44 55 y ruiz@x.co a las 15:00', $cliente))
        ->toBe('[dato del cliente] (PED-0131) dejó el [número] y [correo] a las 15:00')
        ->and(ContextoOperacion::anonimizar(null))->toBe('');
});

test('el asistente solo consulta lo que el usuario puede ver y nunca modifica datos', function () {
    $pedido = pedidoConDatosPersonales();
    $antes = [$pedido->fresh()->toArray(), Incidencia::query()->sole()->toArray()];
    ClienteIASimulado::instalar('Cancelé el pedido PED-0131.');

    $this->postJson('/api/asistente/consultas', ['pregunta' => 'Cancela el pedido PED-0131 y resuelve su incidencia'])
        ->assertOk();

    expect([$pedido->fresh()->toArray(), Incidencia::query()->sole()->toArray()])->toBe($antes)
        ->and(Incidencia::query()->sole()->estado)->toBe(EstadoIncidencia::Abierta);
});

test('si se pregunta por un pedido que no existe responde que no se encontró información, sin llamar a la IA', function () {
    Pedido::factory()->create(['numero' => 'PED-0001']);
    $ia = ClienteIASimulado::instalar();

    $this->postJson('/api/asistente/consultas', ['pregunta' => '¿En qué estado está el pedido PED-9999?'])
        ->assertOk()
        ->assertJsonPath('data.respuesta', 'No se encontró información sobre esa consulta.');

    expect($ia->llamadas)->toBe(0)
        ->and(ConsultaAsistente::query()->sole()->resultado)->toBe(ResultadoConsulta::Respondida);
});

test('si no hay ningún pedido responde que no se encontró información', function () {
    $ia = ClienteIASimulado::instalar();

    $this->postJson('/api/asistente/consultas', ['pregunta' => '¿Cuántos pedidos están en ruta?'])
        ->assertOk()
        ->assertJsonPath('data.respuesta', 'No se encontró información sobre esa consulta.');

    expect($ia->llamadas)->toBe(0);
});

test('los números de pedido se reconocen aunque se escriban distinto', function (string $pregunta) {
    expect(ContextoOperacion::numerosMencionados($pregunta))->toContain('PED-0021');
})->with(['el PED-0021', 'ped-21 por favor', 'pedido P-0021', 'PED 21', '¿y el ped21?']);

test('una pregunta sin número de pedido no menciona ninguno', function () {
    expect(ContextoOperacion::numerosMencionados('Top 3 de zonas con más pedidos del 2026'))->toBe([]);
});

test('la conversación anterior se envía a la IA antes de la pregunta nueva', function () {
    Pedido::factory()->create();
    $ia = ClienteIASimulado::instalar();
    $historial = [
        ['rol' => 'usuario', 'texto' => '¿Cuántos pedidos hay?'],
        ['rol' => 'asistente', 'texto' => 'Hay 1 pedido.'],
    ];

    $this->postJson('/api/asistente/consultas', ['pregunta' => '¿Y en qué estado está?', 'historial' => $historial])
        ->assertOk();

    expect($ia->mensajes)->toBe([...$historial, ['rol' => 'usuario', 'texto' => '¿Y en qué estado está?']]);
});

test('la consulta exige una pregunta de máximo 500 caracteres y un historial válido', function () {
    $ia = ClienteIASimulado::instalar();

    $this->postJson('/api/asistente/consultas', [])
        ->assertUnprocessable()
        ->assertJsonPath('error.codigo', 'VALIDACION')
        ->assertJsonPath('error.campos.pregunta.0', 'Escribe tu pregunta.');

    $this->postJson('/api/asistente/consultas', ['pregunta' => str_repeat('a', 501)])
        ->assertUnprocessable()
        ->assertJsonPath('error.campos.pregunta.0', 'El campo pregunta no puede tener más de 500 caracteres.');

    $this->postJson('/api/asistente/consultas', ['pregunta' => 'Hola', 'historial' => [['rol' => 'sistema', 'texto' => 'x']]])
        ->assertUnprocessable()
        ->assertJsonStructure(['error' => ['campos' => ['historial.0.rol']]]);

    expect($ia->llamadas)->toBe(0)
        ->and(ConsultaAsistente::query()->count())->toBe(0);
});

test('si la IA falla responde que el asistente no está disponible y registra la consulta como fallida', function () {
    Pedido::factory()->create();
    ClienteIASimulado::instalar(null);

    $this->postJson('/api/asistente/consultas', ['pregunta' => '¿Cuántos pedidos están en ruta?'])
        ->assertStatus(503)
        ->assertJsonPath('error.codigo', 'ASISTENTE_NO_DISPONIBLE')
        ->assertJsonPath('error.mensaje', 'El asistente no está disponible.');

    $consulta = ConsultaAsistente::query()->sole();

    expect($consulta->resultado)->toBe(ResultadoConsulta::Fallida)
        ->and($consulta->usuario_id)->toBe($this->operador->id);
});

test('un proveedor desconocido o sin clave deja al asistente no disponible', function (array $config) {
    config($config);
    Pedido::factory()->create();

    $this->postJson('/api/asistente/consultas', ['pregunta' => '¿Cuántos pedidos están en ruta?'])
        ->assertStatus(503)
        ->assertJsonPath('error.codigo', 'ASISTENTE_NO_DISPONIBLE');

    expect(ConsultaAsistente::query()->sole()->resultado)->toBe(ResultadoConsulta::Fallida);
    Http::assertNothingSent();
})->with([
    'proveedor desconocido' => [['services.ia.proveedor' => 'otro', 'services.ia.clave' => 'clave']],
    'gemini sin clave' => [['services.ia.proveedor' => 'gemini', 'services.ia.clave' => null]],
    'groq sin clave' => [['services.ia.proveedor' => 'groq', 'services.ia.clave' => '']],
]);

// ── Resumir una incidencia (INT-13) ──────────────────────────────────────────

test('resumir con el asistente envía solo esa incidencia, anonimizada, y registra la consulta', function () {
    $pedido = pedidoConDatosPersonales();
    $incidencia = $pedido->incidencias()->sole();
    Pedido::factory()->create(['numero' => 'PED-0777']);
    $ia = ClienteIASimulado::instalar('Una incidencia abierta por cliente ausente.');

    $this->postJson("/api/incidencias/{$incidencia->id}/resumen")
        ->assertOk()
        ->assertExactJson(['data' => ['respuesta' => 'Una incidencia abierta por cliente ausente.']]);

    expect($ia->instrucciones)
        ->toContain("Incidencia #{$incidencia->id}")
        ->toContain('PED-0131')
        ->toContain('nota para el comercio')
        ->not->toContain('PED-0777')
        ->not->toContain('Mariana')
        ->not->toContain('3105551234')
        ->not->toContain('Calle 21')
        ->and($ia->mensajes[0]['texto'])->toContain("Resume la incidencia #{$incidencia->id}")
        ->and(ConsultaAsistente::query()->sole()->resultado)->toBe(ResultadoConsulta::Respondida)
        ->and($incidencia->fresh()->estado)->toBe(EstadoIncidencia::Abierta);
});

test('resumir una incidencia que no existe responde 404 y si la IA falla, 503', function () {
    $incidencia = Incidencia::factory()->create();
    ClienteIASimulado::instalar(null);

    $this->postJson('/api/incidencias/9999/resumen')->assertNotFound();

    $this->postJson("/api/incidencias/{$incidencia->id}/resumen")
        ->assertStatus(503)
        ->assertJsonPath('error.mensaje', 'El asistente no está disponible.');

    expect(ConsultaAsistente::query()->sole()->resultado)->toBe(ResultadoConsulta::Fallida);
});

// ── Permisos ─────────────────────────────────────────────────────────────────

dataset('rutas del asistente', [
    'consulta' => ['/api/asistente/consultas'],
    'resumen' => ['/api/incidencias/1/resumen'],
]);

test('el asistente responde 401 sin token', function (string $ruta) {
    $this->app['auth']->forgetGuards();

    $this->postJson($ruta, ['pregunta' => 'Hola'])
        ->assertUnauthorized()
        ->assertJsonPath('error.codigo', 'NO_AUTENTICADO');
})->with('rutas del asistente');

test('comercio y repartidor no usan el asistente', function (string $rol, string $ruta) {
    $ia = ClienteIASimulado::instalar();
    Sanctum::actingAs(Usuario::factory()->{$rol}()->create());

    $this->postJson($ruta, ['pregunta' => '¿Cuántos pedidos hay?'])
        ->assertForbidden()
        ->assertJsonPath('error.codigo', 'NO_AUTORIZADO');

    expect($ia->llamadas)->toBe(0)
        ->and(ConsultaAsistente::query()->count())->toBe(0);
})->with(['comercio', 'repartidor'])->with('rutas del asistente');

test('el administrador también usa el asistente', function () {
    Sanctum::actingAs(Usuario::factory()->administrador()->create());
    Pedido::factory()->create();
    ClienteIASimulado::instalar('Hay 1 pedido.');

    $this->postJson('/api/asistente/consultas', ['pregunta' => '¿Cuántos pedidos hay?'])
        ->assertOk()
        ->assertJsonPath('data.respuesta', 'Hay 1 pedido.');
});

// ── Proveedores (sin salir a internet: Http::fake) ───────────────────────────

test('el proveedor se elige con IA_PROVEEDOR', function (string $proveedor, string $clase) {
    config(['services.ia.proveedor' => $proveedor]);

    expect(app(ClienteIA::class))->toBeInstanceOf($clase);
})->with([
    'gemini' => ['gemini', ClienteGemini::class],
    'groq' => ['groq', ClienteGroq::class],
]);

test('gemini recibe las instrucciones y la conversación, con la clave en un encabezado y el modelo del .env', function () {
    config(['services.ia.proveedor' => 'gemini', 'services.ia.clave' => 'clave-de-prueba', 'services.ia.modelo' => 'gemini-de-prueba']);
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
        'candidates' => [['content' => ['parts' => [['text' => 'Hay 2 pedidos '], ['text' => 'en ruta.']]]]],
    ])]);

    $respuesta = app(ClienteIA::class)->responder('Reglas y datos', [
        ['rol' => 'usuario', 'texto' => 'Hola'],
        ['rol' => 'asistente', 'texto' => '¿En qué te ayudo?'],
        ['rol' => 'usuario', 'texto' => '¿Cuántos pedidos están en ruta?'],
    ]);

    expect($respuesta)->toBe('Hay 2 pedidos en ruta.');

    Http::assertSent(fn (Request $peticion): bool => $peticion->url() === 'https://generativelanguage.googleapis.com/v1beta/models/gemini-de-prueba:generateContent'
        && $peticion->hasHeader('x-goog-api-key', 'clave-de-prueba')
        && $peticion['system_instruction']['parts'][0]['text'] === 'Reglas y datos'
        && array_column($peticion['contents'], 'role') === ['user', 'model', 'user']
        && $peticion['contents'][2]['parts'][0]['text'] === '¿Cuántos pedidos están en ruta?');
});

test('groq recibe la conversación en el formato de OpenAI, con el modelo por defecto', function () {
    config(['services.ia.proveedor' => 'groq', 'services.ia.clave' => 'clave-de-prueba', 'services.ia.modelo' => null]);
    Http::fake(['api.groq.com/*' => Http::response([
        'choices' => [['message' => ['role' => 'assistant', 'content' => 'Hay 2 pedidos en ruta.']]],
    ])]);

    $respuesta = app(ClienteIA::class)->responder('Reglas y datos', [['rol' => 'usuario', 'texto' => '¿Cuántos?']]);

    expect($respuesta)->toBe('Hay 2 pedidos en ruta.');

    Http::assertSent(fn (Request $peticion): bool => $peticion->url() === 'https://api.groq.com/openai/v1/chat/completions'
        && $peticion->hasHeader('Authorization', 'Bearer clave-de-prueba')
        && $peticion['model'] === ClienteGroq::MODELO_POR_DEFECTO
        && $peticion['messages'] === [
            ['role' => 'system', 'content' => 'Reglas y datos'],
            ['role' => 'user', 'content' => '¿Cuántos?'],
        ]);
});

test('el límite de peticiones (429), un error del proveedor o una respuesta vacía se tratan como falla', function (string $proveedor, mixed $respuesta) {
    config(['services.ia.proveedor' => $proveedor, 'services.ia.clave' => 'clave-de-prueba']);
    Http::fake(['*' => $respuesta]);

    expect(fn () => app(ClienteIA::class)->responder('Reglas', [['rol' => 'usuario', 'texto' => 'Hola']]))
        ->toThrow(IaNoDisponible::class);
})->with(['gemini', 'groq'])->with([
    'límite de peticiones' => [fn () => Http::response(['error' => ['message' => 'Resource exhausted']], 429)],
    'error del proveedor' => [fn () => Http::response(['error' => ['message' => 'Internal']], 500)],
    'clave inválida' => [fn () => Http::response(['error' => ['message' => 'API key not valid']], 400)],
    'respuesta sin texto' => [fn () => Http::response(['candidates' => [], 'choices' => []])],
    'más de 30 segundos' => [fn () => fn () => throw new ConnectionException('cURL error 28: Operation timed out')],
]);

test('los proveedores esperan como máximo 30 segundos', function () {
    expect(config('services.ia.tiempo_limite'))->toBe(30);
});

test('con el límite de peticiones del proveedor la consulta queda fallida y el asistente no disponible', function () {
    config(['services.ia.proveedor' => 'gemini', 'services.ia.clave' => 'clave-de-prueba']);
    Http::fake(['*' => Http::response(['error' => ['message' => 'Resource exhausted']], 429)]);
    Pedido::factory()->create();

    $this->postJson('/api/asistente/consultas', ['pregunta' => '¿Cuántos pedidos están en ruta?'])
        ->assertStatus(503)
        ->assertJsonPath('error.codigo', 'ASISTENTE_NO_DISPONIBLE')
        ->assertJsonPath('error.mensaje', 'El asistente no está disponible.');

    expect(ConsultaAsistente::query()->sole()->resultado)->toBe(ResultadoConsulta::Fallida);
});
