<?php

namespace App\Modules\Asistente\Requests;

use App\Modules\Asistente\IA\ClienteIA;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Pregunta para el asistente (INT-14):
 *   { "pregunta": "¿Cuántos pedidos están en ruta?",
 *     "historial": [{ "rol": "usuario", "texto": "..." }, { "rol": "asistente", "texto": "..." }] }
 * El historial es opcional: son los mensajes anteriores de la conversación, para que el
 * asistente entienda preguntas de seguimiento. "Nueva conversación" lo deja vacío.
 */
class ConsultarAsistenteRequest extends FormRequest
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
            'pregunta' => ['required', 'string', 'max:500'],
            'historial' => ['nullable', 'array', 'max:20'],
            'historial.*.rol' => ['required', Rule::in([ClienteIA::USUARIO, ClienteIA::ASISTENTE])],
            'historial.*.texto' => ['required', 'string', 'max:4000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'pregunta.required' => 'Escribe tu pregunta.',
            'historial.max' => 'La conversación es muy larga: empieza una nueva.',
        ];
    }
}
