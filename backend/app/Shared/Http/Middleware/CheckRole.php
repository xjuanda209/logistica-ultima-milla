<?php

namespace App\Shared\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Deja pasar la petición solo si el usuario autenticado tiene uno de los roles indicados.
 *
 * Uso en rutas (alias "rol", registrado en bootstrap/app.php):
 *   Route::get(...)->middleware('rol:administrador,operador');
 */
class CheckRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $usuario = $request->user();

        if ($usuario === null || ! in_array($usuario->rol->value, $roles, true)) {
            throw new AccessDeniedHttpException('No tienes permiso para realizar esta acción.');
        }

        return $next($request);
    }
}
