<?php

use App\Shared\Auth\AuthController;
use Illuminate\Support\Facades\Route;

/*
| Rutas de la API (prefijo /api).
| Cada módulo define las suyas en app/Modules/<Modulo>/routes.php.
*/

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    require app_path('Modules/Administracion/routes.php');
    require app_path('Modules/Pedidos/routes.php');
    require app_path('Modules/Despacho/routes.php');
    require app_path('Modules/Incidencias/routes.php');
    require app_path('Modules/Asistente/routes.php');
});
