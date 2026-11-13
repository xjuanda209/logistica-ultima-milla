<?php

namespace App\Modules\Despacho\Requests;

use App\Shared\Enums\Rol;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Asignar o reasignar un pedido: { "repartidor_id": 7 }.
 * Aquí solo se valida que sea un repartidor; si puede recibir el pedido lo decide el controlador.
 */
class AsignarRepartidorRequest extends FormRequest
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
            'repartidor_id' => ['required', 'integer', Rule::exists('usuarios', 'id')->where('rol', Rol::Repartidor->value)],
        ];
    }
}
