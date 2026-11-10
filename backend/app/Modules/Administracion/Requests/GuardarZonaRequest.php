<?php

namespace App\Modules\Administracion\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida crear (POST) y editar (PUT) una zona.
 */
class GuardarZonaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'nombre' => [
                'required', 'string', 'max:60',
                Rule::unique('zonas', 'nombre')->ignore($this->route('id')),
            ],
            'descripcion' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.unique' => 'Ya existe una zona con ese nombre.',
        ];
    }
}
