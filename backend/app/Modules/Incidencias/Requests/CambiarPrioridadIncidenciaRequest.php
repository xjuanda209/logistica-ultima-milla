<?php

namespace App\Modules\Incidencias\Requests;

use App\Shared\Enums\Prioridad;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Asignar la prioridad de una incidencia (INT-13): { "prioridad": "baja" | "media" | "alta" }.
 */
class CambiarPrioridadIncidenciaRequest extends FormRequest
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
            'prioridad' => ['required', Rule::enum(Prioridad::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'prioridad.required' => 'Elige la prioridad.',
        ];
    }
}
