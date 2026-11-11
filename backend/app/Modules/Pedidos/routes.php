<?php

use App\Modules\Pedidos\Controllers\ClienteController;
use App\Modules\Pedidos\Controllers\PedidoController;
use Illuminate\Support\Facades\Route;

/*
| Rutas del módulo Pedidos. Este archivo se carga desde routes/api.php,
| dentro del grupo protegido con auth:sanctum (todas exigen token).
*/

Route::middleware('rol:administrador,operador,comercio,repartidor')->group(function (): void {
    Route::get('/pedidos', [PedidoController::class, 'index']);
    Route::get('/pedidos/{id}', [PedidoController::class, 'show'])->whereNumber('id');
});

Route::middleware('rol:administrador,operador,comercio')->group(function (): void {
    Route::get('/clientes', [ClienteController::class, 'index']);
    Route::post('/clientes', [ClienteController::class, 'store']);
    Route::put('/clientes/{id}', [ClienteController::class, 'update'])->whereNumber('id');
});
