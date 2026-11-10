<?php

namespace App\Modules\Administracion\Requests;

use App\Shared\Validacion\UnicoSinMayusculas;
use Illuminate\Foundation\Http\FormRequest;

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
                new UnicoSinMayusculas('zonas', 'nombre', $this->route('id'), 'Ya existe una zona con ese nombre.'),
            ],
            'descripcion' => ['nullable', 'string', 'max:500'],
        ];
    }
}
