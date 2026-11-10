<?php

namespace App\Modules\Administracion\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EditarConfiguracionRequest extends FormRequest
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
            'max_pedidos_activos' => ['required', 'integer', 'between:1,20'],
        ];
    }
}
