<?php

namespace App\Modules\Pedidos\Requests;

use App\Modules\Pedidos\Models\Cliente;
use App\Shared\Enums\Rol;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida crear (POST) y editar (PUT) un cliente.
 *
 * El comercio del cliente se decide al crearlo y después no cambia:
 *  - administrador y operador lo eligen (comercio_id);
 *  - al usuario de un comercio se le fuerza el suyo, aunque la petición diga otro.
 */
class GuardarClienteRequest extends FormRequest
{
    private ?Cliente $cliente = null;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Al editar: el cliente de la ruta, solo si el usuario puede verlo (si no, 404).
     */
    public function cliente(): ?Cliente
    {
        if ($this->isMethod('POST')) {
            return null;
        }

        return $this->cliente ??= Cliente::query()
            ->visiblesPara($this->user())
            ->findOrFail($this->route('id'));
    }

    protected function prepareForValidation(): void
    {
        $usuario = $this->user();

        // El comercio nunca lo decide el cliente HTTP cuando no le corresponde.
        if ($this->cliente() !== null) {
            $this->merge(['comercio_id' => $this->cliente()->comercio_id]);
        } elseif ($usuario->rol === Rol::Comercio) {
            $this->merge(['comercio_id' => $usuario->comercio_id]);
        }

        // "1.088.200.222" y "1088200222" son la misma cédula.
        if (is_string($this->input('cedula'))) {
            $this->merge(['cedula' => preg_replace('/[\s.]+/', '', $this->input('cedula'))]);
        }

        if (is_string($this->input('correo'))) {
            $this->merge(['correo' => mb_strtolower(trim($this->input('correo'))) ?: null]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'comercio_id' => ['required', 'integer', Rule::exists('comercios', 'id')],
            'nombre' => ['required', 'string', 'max:120'],
            'cedula' => [
                'required', 'string', 'regex:/^[0-9]{5,15}$/',
                Rule::unique('clientes', 'cedula')
                    ->where('comercio_id', $this->input('comercio_id'))
                    ->ignore($this->route('id')),
            ],
            'telefono' => ['required', 'string', 'regex:/^[0-9+ ]{7,15}$/'],
            'correo' => ['nullable', 'string', 'email', 'max:120'],
            'direccion' => ['required', 'string', 'max:200'],
            'barrio' => ['required', 'string', 'max:80'],
            'indicaciones' => ['nullable', 'string', 'max:500'],
            'zona_id' => ['required', 'integer', Rule::exists('zonas', 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'comercio_id.required' => 'Elige el comercio del cliente.',
            'cedula.unique' => 'Ya existe un cliente con esa cédula en este comercio.',
            'cedula.regex' => 'La cédula debe tener entre 5 y 15 números.',
            'telefono.regex' => 'El teléfono debe tener entre 7 y 15 números.',
            'zona_id.required' => 'Elige la zona de la dirección.',
        ];
    }
}
