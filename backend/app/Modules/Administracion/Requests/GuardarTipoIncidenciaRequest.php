<?php

namespace App\Modules\Administracion\Requests;

use App\Shared\Validacion\UnicoSinMayusculas;
use Illuminate\Foundation\Http\FormRequest;

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
                new UnicoSinMayusculas(
                    'tipos_incidencia', 'nombre', $this->route('id'), 'Ya existe un tipo de incidencia con ese nombre.',
                ),
            ],
        ];
    }
}
