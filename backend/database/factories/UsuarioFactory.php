<?php

namespace Database\Factories;

use App\Modules\Administracion\Models\Comercio;
use App\Modules\Administracion\Models\Usuario;
use App\Modules\Administracion\Models\Zona;
use App\Shared\Enums\Disponibilidad;
use App\Shared\Enums\Rol;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * Por defecto crea un operador. Usa los estados para los demás roles:
 *   Usuario::factory()->comercio($comercio)->create();
 *
 * @extends Factory<Usuario>
 */
class UsuarioFactory extends Factory
{
    protected $model = Usuario::class;

    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => fake()->name(),
            'correo' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'rol' => Rol::Operador,
            'activo' => true,
        ];
    }

    public function administrador(): static
    {
        return $this->state(['rol' => Rol::Administrador]);
    }

    public function operador(): static
    {
        return $this->state(['rol' => Rol::Operador]);
    }

    public function comercio(?Comercio $comercio = null): static
    {
        return $this->state([
            'rol' => Rol::Comercio,
            'comercio_id' => $comercio?->id ?? Comercio::factory(),
        ]);
    }

    public function repartidor(?Zona $zona = null): static
    {
        return $this->state([
            'rol' => Rol::Repartidor,
            'zona_id' => $zona?->id ?? Zona::factory(),
            'disponibilidad' => Disponibilidad::Disponible,
        ]);
    }

    public function inactivo(): static
    {
        return $this->state(['activo' => false]);
    }
}
