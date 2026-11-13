<?php

namespace App\Modules\Incidencias\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Guardar la nota para el comercio (INT-13): { "nota_comercio": "..." }.
 * Es una sola nota por incidencia: se edita, no se acumula; y no puede quedar vacía.
 */
class GuardarNotaComercioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'nota_comercio' => ['required', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nota_comercio.required' => 'La nota no puede estar vacía.',
        ];
    }
}
