<?php

namespace App\Modules\Administracion\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Activar o desactivar un registro: { "activo": true | false }.
 */
class CambiarActivoRequest extends FormRequest
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
            'activo' => ['required', 'boolean'],
        ];
    }
}
