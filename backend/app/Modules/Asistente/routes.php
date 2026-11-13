<?php

use App\Modules\Asistente\Controllers\ConsultaController;
use Illuminate\Support\Facades\Route;

/*
| Rutas del módulo Asistente de IA. Se cargan desde routes/api.php dentro del grupo auth:sanctum.
| El asistente solo informa: ninguna de estas rutas modifica pedidos ni incidencias.
*/

Route::middleware('rol:administrador,operador')->group(function (): void {
    Route::post('/asistente/consultas', [ConsultaController::class, 'store']);
    Route::post('/incidencias/{id}/resumen', [ConsultaController::class, 'resumirIncidencia'])->whereNumber('id');
});
