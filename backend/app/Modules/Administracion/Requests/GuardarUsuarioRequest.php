<?php

namespace App\Modules\Administracion\Requests;

use App\Shared\Enums\Rol;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida crear (POST) y editar (PUT) un usuario.
 * La contraseña solo se pide al crear; después se cambia con "restablecer contraseña".
 */
class GuardarUsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('correo'))) {
            $this->merge(['correo' => mb_strtolower($this->input('correo'))]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $reglas = [
            'nombre' => ['required', 'string', 'max:120'],
            'correo' => [
                'required', 'string', 'email', 'max:120',
                Rule::unique('usuarios', 'correo')->ignore($this->route('id')),
            ],
            'rol' => ['required', Rule::enum(Rol::class)],
            'comercio_id' => ['nullable', 'required_if:rol,comercio', 'integer', Rule::exists('comercios', 'id')],
            'zona_id' => ['nullable', 'required_if:rol,repartidor', 'integer', Rule::exists('zonas', 'id')],
        ];

        if ($this->isMethod('POST')) {
            $reglas['password'] = RestablecerPasswordRequest::reglasPassword();
        }

        return $reglas;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'correo.unique' => 'Ya existe un usuario con ese correo.',
            'comercio_id.required_if' => 'Elige el comercio al que pertenece el usuario.',
            'zona_id.required_if' => 'Elige la zona que cubre el repartidor.',
        ];
    }
}
