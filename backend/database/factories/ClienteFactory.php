<?php

namespace Database\Factories;

use App\Modules\Administracion\Models\Comercio;
use App\Modules\Administracion\Models\Zona;
use App\Modules\Pedidos\Models\Cliente;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cliente>
 */
class ClienteFactory extends Factory
{
    protected $model = Cliente::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'comercio_id' => Comercio::factory(),
            'nombre' => fake()->name(),
            'cedula' => fake()->unique()->numerify('10########'),
            'telefono' => fake()->numerify('3#########'),
            'correo' => fake()->safeEmail(),
            'direccion' => fake()->streetAddress(),
            'barrio' => fake()->citySuffix(),
            'indicaciones' => null,
            'zona_id' => Zona::factory(),
        ];
    }
}
