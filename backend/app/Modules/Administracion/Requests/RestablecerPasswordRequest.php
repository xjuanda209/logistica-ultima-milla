<?php

namespace App\Modules\Administracion\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RestablecerPasswordRequest extends FormRequest
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
            'password' => self::reglasPassword(),
        ];
    }

    /**
     * Reglas de una contraseña (se usan también al crear el usuario).
     * El máximo de 72 es el límite de bcrypt.
     *
     * @return array<int, string>
     */
    public static function reglasPassword(): array
    {
        return ['required', 'string', 'min:8', 'max:72'];
    }
}
