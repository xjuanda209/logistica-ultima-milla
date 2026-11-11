<?php

namespace App\Modules\Pedidos\Requests;

use App\Modules\Pedidos\Models\Pedido;
use App\Shared\Enums\Prioridad;
use App\Shared\Enums\Rol;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * Valida crear (POST) y editar (PUT) un pedido.
 *
 * El comercio y el cliente solo se eligen al crear. Al usuario de un comercio se le fuerza
 * el suyo, aunque la petición diga otro, y el cliente debe ser de ese mismo comercio.
 * Al editar solo cambian prioridad, franja, observaciones y productos.
 */
class GuardarPedidoRequest extends FormRequest
{
    private ?Pedido $pedido = null;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Al editar: el pedido de la ruta, solo si el usuario puede verlo (si no, 404).
     */
    public function pedido(): ?Pedido
    {
        if ($this->isMethod('POST')) {
            return null;
        }

        return $this->pedido ??= Pedido::query()
            ->visiblesPara($this->user())
            ->findOrFail($this->route('id'));
    }

    protected function prepareForValidation(): void
    {
        $this->pedido();

        if ($this->isMethod('POST') && $this->user()->rol === Rol::Comercio) {
            $this->merge(['comercio_id' => $this->user()->comercio_id]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $reglas = [
            'prioridad' => ['required', Rule::enum(Prioridad::class)],
            'franja_inicio' => ['required', 'date', ...($this->franjaInicioCambia() ? ['after:now'] : [])],
            'franja_fin' => ['required', 'date', 'after:franja_inicio'],
            'observaciones' => ['nullable', 'string', 'max:500'],
            'lineas' => ['required', 'array', 'min:1', 'max:50'],
            'lineas.*.producto' => ['required', 'string', 'max:120'],
            'lineas.*.cantidad' => ['required', 'integer', 'gt:0', 'max:9999'],
        ];

        if ($this->isMethod('POST')) {
            $reglas['comercio_id'] = ['required', 'integer', Rule::exists('comercios', 'id')];
            $reglas['cliente_id'] = [
                'required', 'integer',
                Rule::exists('clientes', 'id')->where('comercio_id', $this->input('comercio_id')),
            ];
        }

        return $reglas;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'comercio_id.required' => 'Elige el comercio del pedido.',
            'cliente_id.required' => 'Elige el cliente del pedido.',
            'cliente_id.exists' => 'El cliente elegido no existe en este comercio.',
            'franja_inicio.after' => 'La franja no puede empezar en el pasado.',
            'franja_fin.after' => 'La franja debe terminar después de empezar.',
            'lineas.required' => 'Agrega al menos un producto.',
            'lineas.min' => 'Agrega al menos un producto.',
            'lineas.*.producto.required' => 'Escribe el producto.',
            'lineas.*.cantidad.required' => 'Debe ser mayor que 0.',
            'lineas.*.cantidad.integer' => 'Debe ser un número entero.',
            'lineas.*.cantidad.gt' => 'Debe ser mayor que 0.',
        ];
    }

    /**
     * Al crear, la franja siempre debe empezar en el futuro. Al editar solo se exige si se cambia
     * el inicio: así se pueden corregir las observaciones de un pedido cuya franja ya empezó.
     */
    private function franjaInicioCambia(): bool
    {
        $pedido = $this->pedido();

        if ($pedido === null) {
            return true;
        }

        try {
            return ! Carbon::parse($this->input('franja_inicio'))->equalTo($pedido->franja_inicio);
        } catch (Throwable) {
            return true;
        }
    }
}
