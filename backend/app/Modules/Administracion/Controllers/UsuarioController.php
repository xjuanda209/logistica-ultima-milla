<?php

namespace App\Modules\Administracion\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Administracion\Models\Usuario;
use App\Modules\Administracion\Requests\CambiarActivoRequest;
use App\Modules\Administracion\Requests\GuardarUsuarioRequest;
use App\Modules\Administracion\Requests\RestablecerPasswordRequest;
use App\Modules\Administracion\Resources\UsuarioResource;
use App\Shared\Enums\Disponibilidad;
use App\Shared\Enums\Rol;
use App\Shared\Errores\ErrorApi;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UsuarioController extends Controller
{
    /**
     * GET /api/usuarios — lista paginada de todos los usuarios.
     */
    public function index(): AnonymousResourceCollection
    {
        $usuarios = Usuario::query()
            ->with(['comercio', 'zona'])
            ->orderBy('nombre')
            ->paginate(20);

        return UsuarioResource::collection($usuarios);
    }

    /**
     * POST /api/usuarios — crea un usuario activo con su contraseña inicial.
     */
    public function store(GuardarUsuarioRequest $request): UsuarioResource
    {
        $usuario = Usuario::query()->create([
            ...$this->datosSegunRol($request->validated()),
            'activo' => true,
        ]);

        return new UsuarioResource($usuario->load(['comercio', 'zona']));
    }

    /**
     * PUT /api/usuarios/{id} — edita nombre, correo, rol, comercio y zona.
     */
    public function update(GuardarUsuarioRequest $request, int $id): UsuarioResource
    {
        $usuario = Usuario::query()->findOrFail($id);

        if ($usuario->rol->value !== $request->validated('rol')) {
            if ($usuario->is($request->user())) {
                throw new ErrorApi('NO_PUEDES_CAMBIAR_TU_ROL', 'No puedes cambiar tu propio rol.', 409);
            }

            $this->impedirDejarSinAdministrador($usuario);
        }

        $usuario->update($this->datosSegunRol($request->validated(), $usuario));

        return new UsuarioResource($usuario->load(['comercio', 'zona']));
    }

    /**
     * PUT /api/usuarios/{id}/estado — activa o desactiva. Al desactivar se borran
     * los tokens del usuario, así sus sesiones abiertas dejan de servir de inmediato.
     */
    public function cambiarEstado(CambiarActivoRequest $request, int $id): UsuarioResource
    {
        $usuario = Usuario::query()->findOrFail($id);
        $activo = $request->boolean('activo');

        if (! $activo && $usuario->is($request->user())) {
            throw new ErrorApi(
                'NO_PUEDES_DESACTIVARTE',
                'No puedes desactivar tu propia cuenta: el sistema debe conservar al menos un administrador activo.',
                409,
            );
        }

        if (! $activo) {
            $this->impedirDejarSinAdministrador($usuario);
        }

        $usuario->update(['activo' => $activo]);

        if (! $activo) {
            $usuario->tokens()->delete();
        }

        return new UsuarioResource($usuario->load(['comercio', 'zona']));
    }

    /**
     * PUT /api/usuarios/{id}/password — el administrador define una contraseña nueva.
     * Las sesiones abiertas de ese usuario se cierran (salvo la del propio administrador).
     */
    public function restablecerPassword(RestablecerPasswordRequest $request, int $id): UsuarioResource
    {
        $usuario = Usuario::query()->findOrFail($id);

        $usuario->update(['password' => $request->validated('password')]);

        if ($usuario->isNot($request->user())) {
            $usuario->tokens()->delete();
        }

        return new UsuarioResource($usuario->load(['comercio', 'zona']));
    }

    /**
     * El sistema siempre debe conservar al menos un administrador activo: se llama antes
     * de desactivar a un usuario o de quitarle el rol de administrador.
     */
    private function impedirDejarSinAdministrador(Usuario $usuario): void
    {
        if ($usuario->rol !== Rol::Administrador || ! $usuario->activo) {
            return;
        }

        $hayOtro = Usuario::query()
            ->where('rol', Rol::Administrador)
            ->where('activo', true)
            ->whereKeyNot($usuario->id)
            ->exists();

        if (! $hayOtro) {
            throw new ErrorApi(
                'ULTIMO_ADMINISTRADOR',
                'Es el último administrador activo del sistema: no se puede desactivar ni cambiar de rol.',
                409,
            );
        }
    }

    /**
     * Deja coherentes los campos que dependen del rol: solo el comercio tiene comercio_id
     * y solo el repartidor tiene zona_id y disponibilidad (empieza en "disponible").
     *
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private function datosSegunRol(array $datos, ?Usuario $actual = null): array
    {
        $rol = Rol::from($datos['rol']);

        $datos['comercio_id'] = $rol === Rol::Comercio ? $datos['comercio_id'] : null;
        $datos['zona_id'] = $rol === Rol::Repartidor ? $datos['zona_id'] : null;
        $datos['disponibilidad'] = $rol === Rol::Repartidor
            ? ($actual?->disponibilidad ?? Disponibilidad::Disponible)
            : null;

        return $datos;
    }
}
