<?php

namespace Database\Factories;

use App\Modules\Administracion\Models\Comercio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Comercio>
 */
class ComercioFactory extends Factory
{
    protected $model = Comercio::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => fake()->company(),
            'direccion_recogida' => fake()->streetAddress(),
            'activo' => true,
        ];
    }
}
