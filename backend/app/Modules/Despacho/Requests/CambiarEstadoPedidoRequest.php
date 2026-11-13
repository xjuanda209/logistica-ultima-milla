<?php

namespace App\Modules\Despacho\Requests;

use App\Shared\Enums\EstadoPedido;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Cambio de estado que hace el repartidor (INT-16 e INT-17):
 *   { "estado": "recogido" | "en_ruta" | "entregado" | "no_entregado", "motivo": "..." }
 * El motivo solo se exige para "no_entregado". Si la transición se permite lo decide EstadoPedido.
 */
class CambiarEstadoPedidoRequest extends FormRequest
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
            'estado' => ['required', Rule::in([
                EstadoPedido::Recogido->value,
                EstadoPedido::EnRuta->value,
                EstadoPedido::Entregado->value,
                EstadoPedido::NoEntregado->value,
            ])],
            'motivo' => ['nullable', 'required_if:estado,'.EstadoPedido::NoEntregado->value, 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'motivo.required_if' => 'Escribe el motivo del no entregado.',
        ];
    }
}
