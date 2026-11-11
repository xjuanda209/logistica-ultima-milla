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
     * Correo inexistente, contraseña incorrecta o cuenta desactivada responden lo mismo
     * (INT-01): así no se revela qué correos existen ni cuáles están desactivados.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $usuario = Usuario::query()->where('correo', $request->string('correo')->lower()->toString())->first();

        $passwordCorrecta = $usuario !== null
            && Hash::check($request->string('password')->toString(), $usuario->password);

        if (! $passwordCorrecta || ! $usuario->activo) {
            throw new ErrorApi('CREDENCIALES_INVALIDAS', 'Correo o contraseña incorrectos.', 401);
        }

        return response()->json([
            'token' => $usuario->createToken('api')->plainTextToken,
            'usuario' => new UsuarioResource($usuario->load('comercio:id,nombre')),
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
        return new UsuarioResource($request->user()->load('comercio:id,nombre'));
    }
}
