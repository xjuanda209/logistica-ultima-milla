<?php

namespace Database\Factories;

use App\Modules\Administracion\Models\Comercio;
use App\Modules\Administracion\Models\Usuario;
use App\Modules\Administracion\Models\Zona;
use App\Modules\Pedidos\Models\Cliente;
use App\Modules\Pedidos\Models\Pedido;
use App\Shared\Enums\EstadoPedido;
use App\Shared\Enums\Prioridad;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Crea un pedido en estado "creado", con su comercio, cliente y zona.
 *   Pedido::factory()->for($comercio)->create();
 *   Pedido::factory()->asignadoA($repartidor)->create();
 *
 * @extends Factory<Pedido>
 */
class PedidoFactory extends Factory
{
    protected $model = Pedido::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'numero' => 'PED-'.fake()->unique()->numerify('######'),
            'comercio_id' => Comercio::factory(),
            'zona_id' => Zona::factory(),
            'cliente_id' => fn (array $atributos) => Cliente::factory()->state([
                'comercio_id' => $atributos['comercio_id'],
                'zona_id' => $atributos['zona_id'],
            ]),
            'repartidor_id' => null,
            'creado_por' => Usuario::factory(),
            'estado' => EstadoPedido::Creado,
            'prioridad' => Prioridad::Media,
            'direccion' => fake()->streetAddress(),
            'barrio' => fake()->citySuffix(),
            'indicaciones' => null,
            'franja_inicio' => now()->addDay()->setTime(8, 0),
            'franja_fin' => now()->addDay()->setTime(12, 0),
            'observaciones' => null,
        ];
    }

    public function asignadoA(Usuario $repartidor): static
    {
        return $this->state([
            'repartidor_id' => $repartidor->id,
            'estado' => EstadoPedido::Asignado,
        ]);
    }
}
