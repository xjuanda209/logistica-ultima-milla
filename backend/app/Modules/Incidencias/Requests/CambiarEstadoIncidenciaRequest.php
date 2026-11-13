<?php

namespace App\Modules\Incidencias\Requests;

use App\Shared\Enums\EstadoIncidencia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Avanzar el estado de una incidencia (INT-13):
 *   { "estado": "en_revision" }
 *   { "estado": "resuelta", "resolucion": "Se reprogramó la entrega para las 15:00." }
 * Si la transición se permite lo decide EstadoIncidencia (el controlador responde 409 si no).
 */
class CambiarEstadoIncidenciaRequest extends FormRequest
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
            'estado' => ['required', Rule::in([EstadoIncidencia::EnRevision->value, EstadoIncidencia::Resuelta->value])],
            'resolucion' => [
                Rule::requiredIf($this->input('estado') === EstadoIncidencia::Resuelta->value),
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'resolucion.required' => 'Escribe la resolución para marcar la incidencia como resuelta.',
        ];
    }
}
