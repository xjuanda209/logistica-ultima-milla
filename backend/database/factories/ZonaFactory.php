<?php

namespace Database\Factories;

use App\Modules\Administracion\Models\Zona;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Zona>
 */
class ZonaFactory extends Factory
{
    protected $model = Zona::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => 'Zona '.fake()->unique()->numerify('####'),
            'descripcion' => fake()->sentence(),
        ];
    }
}
