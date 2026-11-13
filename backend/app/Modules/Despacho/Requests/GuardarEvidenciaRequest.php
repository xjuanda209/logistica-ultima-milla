<?php

namespace App\Modules\Despacho\Requests;

use App\Shared\Enums\TipoEvidencia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Registrar una evidencia (INT-18), como multipart/form-data:
 *   tipo = foto_entrega | comprobante, archivo = imagen JPG o PNG de máximo 5 MB,
 *   latitud y longitud opcionales (si el teléfono no dio permiso de ubicación no se envían).
 */
class GuardarEvidenciaRequest extends FormRequest
{
    public const MENSAJE_ARCHIVO = 'La imagen debe ser JPG o PNG y pesar 5 MB o menos.';

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
            'tipo' => ['required', Rule::enum(TipoEvidencia::class)],
            'archivo' => ['required', 'file', 'mimes:jpg,jpeg,png', 'max:5120'],
            'latitud' => ['nullable', 'required_with:longitud', 'numeric', 'between:-90,90'],
            'longitud' => ['nullable', 'required_with:latitud', 'numeric', 'between:-180,180'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'archivo.required' => 'Toma una foto o elige una imagen de la galería.',
            'archivo.file' => self::MENSAJE_ARCHIVO,
            'archivo.mimes' => self::MENSAJE_ARCHIVO,
            'archivo.max' => self::MENSAJE_ARCHIVO,
            'archivo.uploaded' => self::MENSAJE_ARCHIVO,
        ];
    }
}
