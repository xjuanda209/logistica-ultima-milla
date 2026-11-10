<?php

namespace App\Shared\Auth;

use App\Http\Controllers\Controller;
use App\Modules\Administracion\Models\Usuario;
use App\Modules\Administracion\Resources\UsuarioResource;
use App\Shared\Errores\ErrorApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * POST /api/login — valida correo y contraseña y entrega un token de Sanctum.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $usuario = Usuario::query()->where('correo', $request->string('correo')->lower()->toString())->first();

        if ($usuario === null || ! Hash::check($request->string('password')->toString(), $usuario->password)) {
            throw new ErrorApi('CREDENCIALES_INVALIDAS', 'Correo o contraseña incorrectos.', 401);
        }

        if (! $usuario->activo) {
            throw new ErrorApi('USUARIO_INACTIVO', 'Tu usuario está inactivo. Contacta al administrador.', 403);
        }

        return response()->json([
            'token' => $usuario->createToken('api')->plainTextToken,
            'usuario' => new UsuarioResource($usuario->load('comercio')),
        ]);
    }

    /**
     * POST /api/logout — elimina el token con el que se hizo la petición.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['mensaje' => 'Sesión cerrada.']);
    }

    /**
     * GET /api/me — datos del usuario autenticado.
     */
    public function me(Request $request): UsuarioResource
    {
        return new UsuarioResource($request->user()->load('comercio'));
    }
}
