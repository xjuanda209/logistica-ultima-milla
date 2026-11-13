<?php

use App\Modules\Incidencias\Controllers\IncidenciaController;
use Illuminate\Support\Facades\Route;

/*
| Rutas del módulo Incidencias. Se cargan desde routes/api.php dentro del grupo auth:sanctum.
*/

Route::middleware('rol:administrador,operador,comercio,repartidor')->group(function (): void {
    Route::get('/incidencias', [IncidenciaController::class, 'index']);
});

// El repartidor reporta incidencias de sus pedidos desde la app móvil (INT-19).
Route::middleware('rol:repartidor')->group(function (): void {
    Route::post('/pedidos/{id}/incidencias', [IncidenciaController::class, 'store'])->whereNumber('id');
});
