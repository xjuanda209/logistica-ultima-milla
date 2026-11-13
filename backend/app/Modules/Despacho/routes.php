<?php

use App\Modules\Despacho\Controllers\DespachoController;
use App\Modules\Despacho\Controllers\PanelController;
use App\Modules\Despacho\Controllers\RepartidorController;
use Illuminate\Support\Facades\Route;

/*
| Rutas del módulo Despacho (panel, asignación, seguimiento y evidencias).
| Se cargan desde routes/api.php dentro del grupo auth:sanctum.
*/

Route::middleware('rol:administrador,operador,comercio')->group(function (): void {
    Route::get('/panel/resumen', [PanelController::class, 'resumen']);
});

Route::middleware('rol:administrador,operador')->group(function (): void {
    Route::get('/pedidos/{id}/repartidores', [DespachoController::class, 'candidatos'])->whereNumber('id');
    Route::put('/pedidos/{id}/repartidor', [DespachoController::class, 'asignar'])->whereNumber('id');
    Route::put('/pedidos/{id}/cancelacion', [DespachoController::class, 'cancelar'])->whereNumber('id');

    Route::get('/repartidores', [RepartidorController::class, 'index']);
});

// El repartidor solo cambia su propia disponibilidad (lo controla el controlador).
Route::middleware('rol:administrador,operador,repartidor')->group(function (): void {
    Route::put('/repartidores/{id}/disponibilidad', [RepartidorController::class, 'cambiarDisponibilidad'])->whereNumber('id');
});
