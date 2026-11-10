<?php

use App\Modules\Administracion\Controllers\ComercioController;
use App\Modules\Administracion\Controllers\ConfiguracionController;
use App\Modules\Administracion\Controllers\TipoIncidenciaController;
use App\Modules\Administracion\Controllers\UsuarioController;
use App\Modules\Administracion\Controllers\ZonaController;
use Illuminate\Support\Facades\Route;

/*
| Rutas del módulo Administración (usuarios, zonas, tipos de incidencia, configuración).
| Se cargan desde routes/api.php dentro del grupo auth:sanctum.
*/

// Catálogos: cualquier usuario autenticado puede LEERLOS (los usan los formularios de
// Pedidos e Incidencias). Crear, editar y activar/desactivar es solo del administrador.
Route::middleware('rol:administrador,operador,comercio,repartidor')->group(function (): void {
    Route::get('/zonas', [ZonaController::class, 'index']);
    Route::get('/comercios', [ComercioController::class, 'index']);
    Route::get('/tipos-incidencia', [TipoIncidenciaController::class, 'index']);
});

Route::middleware('rol:administrador')->group(function (): void {
    Route::get('/usuarios', [UsuarioController::class, 'index']);
    Route::post('/usuarios', [UsuarioController::class, 'store']);
    Route::put('/usuarios/{id}', [UsuarioController::class, 'update'])->whereNumber('id');
    Route::put('/usuarios/{id}/estado', [UsuarioController::class, 'cambiarEstado'])->whereNumber('id');
    Route::put('/usuarios/{id}/password', [UsuarioController::class, 'restablecerPassword'])->whereNumber('id');

    Route::post('/zonas', [ZonaController::class, 'store']);
    Route::put('/zonas/{id}', [ZonaController::class, 'update'])->whereNumber('id');

    Route::post('/tipos-incidencia', [TipoIncidenciaController::class, 'store']);
    Route::put('/tipos-incidencia/{id}', [TipoIncidenciaController::class, 'update'])->whereNumber('id');
    Route::put('/tipos-incidencia/{id}/estado', [TipoIncidenciaController::class, 'cambiarEstado'])->whereNumber('id');

    Route::get('/configuracion', [ConfiguracionController::class, 'show']);
    Route::put('/configuracion', [ConfiguracionController::class, 'update']);
});
