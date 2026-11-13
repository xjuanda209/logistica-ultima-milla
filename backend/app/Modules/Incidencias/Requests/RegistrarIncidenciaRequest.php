<?php

namespace App\Modules\Incidencias\Requests;

use App\Modules\Despacho\Requests\GuardarEvidenciaRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Registrar una incidencia sobre un pedido (INT-19), como multipart/form-data:
 *   tipo_incidencia_id (solo tipos activos), descripcion y foto opcional (JPG o PNG, máximo 5 MB).
 */
class RegistrarIncidenciaRequest extends FormRequest
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
            'tipo_incidencia_id' => ['required', 'integer', Rule::exists('tipos_incidencia', 'id')->where('activo', true)],
            'descripcion' => ['required', 'string', 'max:1000'],
            'foto' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:5120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tipo_incidencia_id.required' => 'Elige el tipo de la incidencia.',
            'tipo_incidencia_id.exists' => 'Elige el tipo de la incidencia.',
            'descripcion.required' => 'Escribe qué ocurrió.',
            'foto.file' => GuardarEvidenciaRequest::MENSAJE_ARCHIVO,
            'foto.mimes' => GuardarEvidenciaRequest::MENSAJE_ARCHIVO,
            'foto.max' => GuardarEvidenciaRequest::MENSAJE_ARCHIVO,
            'foto.uploaded' => GuardarEvidenciaRequest::MENSAJE_ARCHIVO,
        ];
    }
}
