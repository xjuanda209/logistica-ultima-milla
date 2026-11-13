<?php

use App\Modules\Incidencias\Controllers\IncidenciaController;
use Illuminate\Support\Facades\Route;

/*
| Rutas del módulo Incidencias. Se cargan desde routes/api.php dentro del grupo auth:sanctum.
*/

Route::middleware('rol:administrador,operador,comercio,repartidor')->group(function (): void {
    Route::get('/incidencias', [IncidenciaController::class, 'index']);
    Route::get('/incidencias/{id}', [IncidenciaController::class, 'show'])->whereNumber('id');
});

// El repartidor las reporta desde la app móvil y el operador desde el detalle del pedido (INT-19).
Route::middleware('rol:administrador,operador,repartidor')->group(function (): void {
    Route::post('/pedidos/{id}/incidencias', [IncidenciaController::class, 'store'])->whereNumber('id');
});

// Gestión de la incidencia (INT-13): el comercio solo consulta.
Route::middleware('rol:administrador,operador')->group(function (): void {
    Route::put('/incidencias/{id}/prioridad', [IncidenciaController::class, 'cambiarPrioridad'])->whereNumber('id');
    Route::put('/incidencias/{id}/estado', [IncidenciaController::class, 'cambiarEstado'])->whereNumber('id');
    Route::put('/incidencias/{id}/nota', [IncidenciaController::class, 'guardarNota'])->whereNumber('id');
});
