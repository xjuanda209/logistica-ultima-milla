<?php

use App\Modules\Administracion\Controllers\ComercioController;
use App\Modules\Administracion\Controllers\UsuarioController;
use Illuminate\Support\Facades\Route;

/*
| Rutas del módulo Administración (usuarios, zonas, tipos de incidencia, configuración).
| Se cargan desde routes/api.php dentro del grupo auth:sanctum.
| Todo el módulo es exclusivo del administrador.
*/

Route::middleware('rol:administrador')->group(function (): void {
    Route::get('/usuarios', [UsuarioController::class, 'index']);
    Route::post('/usuarios', [UsuarioController::class, 'store']);
    Route::put('/usuarios/{id}', [UsuarioController::class, 'update'])->whereNumber('id');
    Route::put('/usuarios/{id}/estado', [UsuarioController::class, 'cambiarEstado'])->whereNumber('id');
    Route::put('/usuarios/{id}/password', [UsuarioController::class, 'restablecerPassword'])->whereNumber('id');

    Route::get('/comercios', [ComercioController::class, 'index']);
});
