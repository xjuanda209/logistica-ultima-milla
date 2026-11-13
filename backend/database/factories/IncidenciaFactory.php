<?php

namespace Database\Factories;

use App\Modules\Administracion\Models\Usuario;
use App\Modules\Incidencias\Models\Incidencia;
use App\Modules\Incidencias\Models\TipoIncidencia;
use App\Modules\Pedidos\Models\Pedido;
use App\Shared\Enums\EstadoIncidencia;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Crea una incidencia abierta y sin prioridad, con su pedido, tipo y usuario que la reportó.
 *   Incidencia::factory()->for($pedido)->create();
 *   Incidencia::factory()->enRevision()->create();
 *
 * @extends Factory<Incidencia>
 */
class IncidenciaFactory extends Factory
{
    protected $model = Incidencia::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pedido_id' => Pedido::factory(),
            'tipo_incidencia_id' => TipoIncidencia::factory(),
            'usuario_id' => Usuario::factory(),
            'descripcion' => fake()->sentence(),
            'estado' => EstadoIncidencia::Abierta,
        ];
    }

    public function enRevision(): static
    {
        return $this->state(['estado' => EstadoIncidencia::EnRevision]);
    }

    public function resuelta(): static
    {
        return $this->state([
            'estado' => EstadoIncidencia::Resuelta,
            'resolucion' => 'Se reprogramó la entrega.',
        ]);
    }
}
