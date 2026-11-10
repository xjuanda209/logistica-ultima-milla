<?php

namespace App\Shared\Errores;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Convierte cualquier error de la API al formato único del proyecto:
 *
 *   {"error": {"codigo": "VALIDACION", "mensaje": "...", "campos": {"correo": ["..."]}}}
 *
 * Se registra en bootstrap/app.php.
 */
class ManejadorErrores
{
    public static function registrar(Exceptions $exceptions): void
    {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request): bool => self::esPeticionApi($request),
        );

        $exceptions->render(function (Throwable $e, Request $request): ?JsonResponse {
            if (! self::esPeticionApi($request) || $e instanceof HttpResponseException) {
                return null;
            }

            return self::aRespuesta($e);
        });
    }

    public static function aRespuesta(Throwable $e): JsonResponse
    {
        return match (true) {
            $e instanceof ErrorApi => self::respuesta($e->codigo, $e->getMessage(), $e->estadoHttp, $e->campos),
            $e instanceof ValidationException => self::respuesta('VALIDACION', 'Los datos enviados no son válidos.', 422, $e->errors()),
            $e instanceof AuthenticationException => self::respuesta('NO_AUTENTICADO', 'Debes iniciar sesión.', 401),
            $e instanceof HttpExceptionInterface => self::deErrorHttp($e),
            default => self::respuesta(
                'ERROR_INTERNO',
                config('app.debug') ? $e->getMessage() : 'Ocurrió un error inesperado.',
                500,
            ),
        };
    }

    /**
     * @param  array<string, array<int, string>>  $campos
     */
    public static function respuesta(string $codigo, string $mensaje, int $estadoHttp, array $campos = []): JsonResponse
    {
        return response()->json([
            'error' => [
                'codigo' => $codigo,
                'mensaje' => $mensaje,
                'campos' => (object) $campos,
            ],
        ], $estadoHttp);
    }

    private static function deErrorHttp(HttpExceptionInterface $e): JsonResponse
    {
        $estado = $e->getStatusCode();

        [$codigo, $mensaje] = match ($estado) {
            403 => ['NO_AUTORIZADO', 'No tienes permiso para realizar esta acción.'],
            404 => ['NO_ENCONTRADO', 'El recurso solicitado no existe.'],
            405 => ['METODO_NO_PERMITIDO', 'Método HTTP no permitido para esta ruta.'],
            429 => ['DEMASIADAS_PETICIONES', 'Demasiadas peticiones. Intenta de nuevo en un momento.'],
            default => ['ERROR_HTTP', 'No se pudo procesar la petición.'],
        };

        return self::respuesta($codigo, $mensaje, $estado);
    }

    private static function esPeticionApi(Request $request): bool
    {
        return $request->is('api/*') || $request->expectsJson();
    }
}
