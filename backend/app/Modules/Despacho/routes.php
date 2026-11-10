<?php

use App\Modules\Despacho\Controllers\PanelController;
use Illuminate\Support\Facades\Route;

/*
| Rutas del módulo Despacho (panel, asignación, seguimiento y evidencias).
| Se cargan desde routes/api.php dentro del grupo auth:sanctum.
*/

Route::middleware('rol:administrador,operador,comercio')->group(function (): void {
    Route::get('/panel/resumen', [PanelController::class, 'resumen']);
});
