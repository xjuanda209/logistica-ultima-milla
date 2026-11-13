<?php

namespace App\Modules\Despacho\Requests;

use App\Shared\Enums\Disponibilidad;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Cambiar la disponibilidad de un repartidor: { "disponibilidad": "disponible" | "no_disponible" }.
 */
class CambiarDisponibilidadRequest extends FormRequest
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
            'disponibilidad' => ['required', Rule::enum(Disponibilidad::class)],
        ];
    }
}
