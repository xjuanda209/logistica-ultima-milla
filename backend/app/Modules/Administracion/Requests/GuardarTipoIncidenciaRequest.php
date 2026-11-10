<?php

namespace App\Modules\Administracion\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida crear (POST) y editar (PUT) un tipo de incidencia.
 */
class GuardarTipoIncidenciaRequest extends FormRequest
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
                Rule::unique('tipos_incidencia', 'nombre')->ignore($this->route('id')),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.unique' => 'Ya existe un tipo de incidencia con ese nombre.',
        ];
    }
}
