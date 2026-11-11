<?php

use App\Modules\Administracion\Models\Usuario;
use App\Modules\Despacho\Models\Evidencia;
use App\Modules\Incidencias\Models\Incidencia;
use App\Modules\Incidencias\Models\TipoIncidencia;
use App\Modules\Pedidos\Models\Pedido;
use App\Shared\Enums\Canal;
use App\Shared\Enums\EstadoPedido;
use App\Shared\Enums\TipoEvento;
use App\Shared\Enums\TipoEvidencia;
use Laravel\Sanctum\Sanctum;

test('el detalle trae los datos del pedido, sus productos, quién lo creó y las fechas', function () {
    $creador = Usuario::factory()->operador()->create(['nombre' => 'Óscar Operador']);
    $repartidor = Usuario::factory()->repartidor()->create(['nombre' => 'Rubén Repartidor']);
    $pedido = Pedido::factory()->asignadoA($repartidor)->create(['creado_por' => $creador->id, 'observaciones' => 'Portería']);
    $pedido->lineas()->create(['producto' => 'Pan de bono x12', 'cantidad' => 2]);

    Sanctum::actingAs($creador);

    $this->getJson("/api/pedidos/{$pedido->id}")
        ->assertOk()
        ->assertJsonPath('data.numero', $pedido->numero)
        ->assertJsonPath('data.estado', 'asignado')
        ->assertJsonPath('data.prioridad', 'media')
        ->assertJsonPath('data.creado_por', 'Óscar Operador')
        ->assertJsonPath('data.repartidor.nombre', 'Rubén Repartidor')
        ->assertJsonPath('data.observaciones', 'Portería')
        ->assertJsonPath('data.lineas.0.producto', 'Pan de bono x12')
        ->assertJsonPath('data.incidencias', [])
        ->assertJsonPath('data.evidencias', [])
        ->assertJsonStructure(['data' => [
            'direccion', 'barrio', 'indicaciones', 'franja_inicio', 'franja_fin', 'created_at', 'updated_at',
            'comercio' => ['id', 'nombre'], 'cliente' => ['id', 'nombre', 'telefono'], 'zona' => ['id', 'nombre'],
        ]]);
});

test('el historial del detalle trae cada evento con su usuario, estados y canal', function () {
    $operador = Usuario::factory()->operador()->create(['nombre' => 'Óscar Operador']);
    $pedido = Pedido::factory()->create(['estado' => EstadoPedido::Asignado]);
    $pedido->historial()->create([
        'usuario_id' => $operador->id, 'tipo_evento' => TipoEvento::Creacion, 'estado_nuevo' => EstadoPedido::Creado,
        'detalle' => 'Pedido creado', 'canal' => Canal::Web, 'created_at' => now()->subHour(),
    ]);
    $pedido->historial()->create([
        'usuario_id' => $operador->id, 'tipo_evento' => TipoEvento::Asignacion, 'estado_anterior' => EstadoPedido::Creado,
        'estado_nuevo' => EstadoPedido::Asignado, 'detalle' => 'Asignado a Rubén', 'canal' => Canal::Web, 'created_at' => now(),
    ]);

    Sanctum::actingAs($operador);

    $this->getJson("/api/pedidos/{$pedido->id}")
        ->assertOk()
        ->assertJsonCount(2, 'data.historial')
        ->assertJsonPath('data.historial.0.tipo_evento', 'creacion')
        ->assertJsonPath('data.historial.0.cambios', null)
        ->assertJsonPath('data.historial.1.tipo_evento', 'asignacion')
        ->assertJsonPath('data.historial.1.estado_anterior', 'creado')
        ->assertJsonPath('data.historial.1.estado_nuevo', 'asignado')
        ->assertJsonPath('data.historial.1.detalle', 'Asignado a Rubén')
        ->assertJsonPath('data.historial.1.usuario', 'Óscar Operador')
        ->assertJsonPath('data.historial.1.canal', 'web');
});

test('el detalle resume las incidencias y evidencias del pedido', function () {
    $repartidor = Usuario::factory()->repartidor()->create();
    $pedido = Pedido::factory()->asignadoA($repartidor)->create();
    $incidencia = Incidencia::query()->create([
        'pedido_id' => $pedido->id,
        'tipo_incidencia_id' => TipoIncidencia::factory()->create(['nombre' => 'Cliente ausente'])->id,
        'usuario_id' => $repartidor->id,
        'descripcion' => 'Nadie atendió.',
    ]);
    Evidencia::query()->create([
        'pedido_id' => $pedido->id, 'usuario_id' => $repartidor->id, 'tipo' => TipoEvidencia::FotoEntrega,
        'ruta_archivo' => 'evidencias/foto.jpg', 'latitud' => 4.813, 'longitud' => -75.696,
    ]);
    Evidencia::query()->create([
        'pedido_id' => $pedido->id, 'usuario_id' => $repartidor->id, 'tipo' => TipoEvidencia::Comprobante,
        'ruta_archivo' => 'evidencias/comprobante.jpg',
    ]);

    Sanctum::actingAs(Usuario::factory()->operador()->create());

    $this->getJson("/api/pedidos/{$pedido->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data.incidencias')
        ->assertJsonPath('data.incidencias.0.id', $incidencia->id)
        ->assertJsonPath('data.incidencias.0.tipo', 'Cliente ausente')
        ->assertJsonPath('data.incidencias.0.estado', 'abierta')
        ->assertJsonCount(2, 'data.evidencias')
        ->assertJsonPath('data.evidencias.0.tipo', 'foto_entrega')
        ->assertJsonPath('data.evidencias.0.con_ubicacion', true)
        ->assertJsonPath('data.evidencias.1.tipo', 'comprobante')
        ->assertJsonPath('data.evidencias.1.con_ubicacion', false)
        ->assertJsonMissingPath('data.evidencias.0.ruta_archivo');
});

test('el detalle de un pedido que no existe responde 404', function () {
    Sanctum::actingAs(Usuario::factory()->operador()->create());

    $this->getJson('/api/pedidos/9999')
        ->assertNotFound()
        ->assertJsonPath('error.codigo', 'NO_ENCONTRADO');
});

test('el detalle de un pedido responde 401 sin token', function () {
    $pedido = Pedido::factory()->create();

    $this->getJson("/api/pedidos/{$pedido->id}")
        ->assertUnauthorized()
        ->assertJsonPath('error.codigo', 'NO_AUTENTICADO');
});
