<?php

namespace App\Modules\Despacho\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Cancelar un pedido (INT-10): { "motivo": "El cliente ya no lo necesita." }.
 */
class CancelarPedidoRequest extends FormRequest
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
            'motivo' => ['required', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'motivo.required' => 'Escribe el motivo de la cancelación.',
        ];
    }
}
