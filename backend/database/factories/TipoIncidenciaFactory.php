<?php

namespace Database\Factories;

use App\Modules\Incidencias\Models\TipoIncidencia;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TipoIncidencia>
 */
class TipoIncidenciaFactory extends Factory
{
    protected $model = TipoIncidencia::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => 'Tipo '.fake()->unique()->numerify('####'),
            'activo' => true,
        ];
    }

    public function inactivo(): static
    {
        return $this->state(['activo' => false]);
    }
}
