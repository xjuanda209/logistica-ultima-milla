<?php

use App\Modules\Administracion\Models\Usuario;
use App\Modules\Incidencias\Models\Incidencia;
use App\Modules\Incidencias\Models\TipoIncidencia;
use App\Modules\Pedidos\Models\Cliente;
use App\Modules\Pedidos\Models\HistorialPedido;
use App\Modules\Pedidos\Models\LineaPedido;
use App\Modules\Pedidos\Models\Pedido;
use App\Shared\Enums\Canal;
use App\Shared\Enums\TipoEvento;
use Illuminate\Database\LazyLoadingViolationException;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

/*
| Las listas cargan sus relaciones por adelantado: la cantidad de consultas no crece con la
| cantidad de registros (sin N+1). Fuera de producción, Model::preventLazyLoading() hace que
| cualquier relación sin cargar falle en las pruebas (ver AppServiceProvider).
*/

/**
 * Cuántas consultas a la base hace la petición.
 */
function consultasDe(string $ruta): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();

    test()->getJson($ruta)->assertOk();

    $cantidad = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $cantidad;
}

function crearIncidencia(): Incidencia
{
    return Incidencia::query()->create([
        'pedido_id' => Pedido::factory()->create()->id,
        'tipo_incidencia_id' => TipoIncidencia::factory()->create()->id,
        'usuario_id' => Usuario::factory()->repartidor()->create()->id,
        'descripcion' => 'El cliente no estaba.',
    ]);
}

test('las listas hacen las mismas consultas con 1 registro que con 6', function (string $ruta, Closure $crear) {
    Sanctum::actingAs(Usuario::factory()->administrador()->create());

    $crear();
    $conUno = consultasDe($ruta);

    foreach (range(1, 5) as $vez) {
        $crear();
    }

    expect(consultasDe($ruta))->toBe($conUno);
})->with([
    'usuarios' => ['/api/usuarios', fn () => [
        Usuario::factory()->comercio()->create(),
        Usuario::factory()->repartidor()->create(),
    ]],
    'zonas con repartidores' => ['/api/zonas?con_repartidores=1', fn () => Usuario::factory()->repartidor()->create()],
    'pedidos' => ['/api/pedidos', fn () => Pedido::factory()->create()],
    'clientes' => ['/api/clientes', fn () => Cliente::factory()->create()],
    'incidencias' => ['/api/incidencias', fn () => crearIncidencia()],
]);

test('el detalle de un pedido hace las mismas consultas sin importar cuántas líneas e historial tenga', function () {
    Sanctum::actingAs(Usuario::factory()->operador()->create());
    $pedido = Pedido::factory()->create();

    $agregarEventos = function (int $cantidad) use ($pedido): void {
        foreach (range(1, $cantidad) as $vez) {
            LineaPedido::query()->create(['pedido_id' => $pedido->id, 'producto' => 'Pan', 'cantidad' => 1]);
            HistorialPedido::query()->create([
                'pedido_id' => $pedido->id,
                'usuario_id' => Usuario::factory()->create()->id,
                'tipo_evento' => TipoEvento::Edicion,
                'canal' => Canal::Web,
                'created_at' => now(),
            ]);
        }
    };

    $agregarEventos(1);
    $conUno = consultasDe("/api/pedidos/{$pedido->id}");

    $agregarEventos(5);

    expect(consultasDe("/api/pedidos/{$pedido->id}"))->toBe($conUno);
});

test('usar una relación que no se cargó por adelantado lanza un error fuera de producción', function () {
    // Laravel solo lo detecta cuando la consulta trae varios modelos (el caso del N+1).
    Usuario::factory()->comercio()->count(2)->create();

    expect(fn () => Usuario::query()->get()->each(fn (Usuario $usuario) => $usuario->comercio))
        ->toThrow(LazyLoadingViolationException::class);
});
