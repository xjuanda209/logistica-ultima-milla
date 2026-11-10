<?php

namespace Database\Seeders;

use App\Modules\Administracion\Models\Comercio;
use App\Modules\Administracion\Models\Usuario;
use App\Modules\Administracion\Models\Zona;
use App\Shared\Enums\Disponibilidad;
use App\Shared\Enums\Rol;
use Illuminate\Database\Seeder;

/**
 * Un usuario de prueba por rol (más el usuario del segundo comercio).
 * Son datos ficticios: la contraseña de todos es "password".
 */
class UsuariosSeeder extends Seeder
{
    public function run(): void
    {
        $tienda = Comercio::query()->where('nombre', 'Tienda La Esquina')->firstOrFail();
        $farmacia = Comercio::query()->where('nombre', 'Farmacia Vida Sana')->firstOrFail();
        $centro = Zona::query()->where('nombre', 'Centro')->firstOrFail();

        $usuarios = [
            ['nombre' => 'Ana Administradora', 'correo' => 'admin@logistica.test', 'rol' => Rol::Administrador],
            ['nombre' => 'Óscar Operador', 'correo' => 'operador@logistica.test', 'rol' => Rol::Operador],
            ['nombre' => 'Camila Comercio', 'correo' => 'comercio@logistica.test', 'rol' => Rol::Comercio, 'comercio_id' => $tienda->id],
            ['nombre' => 'Fabián Farmacia', 'correo' => 'comercio2@logistica.test', 'rol' => Rol::Comercio, 'comercio_id' => $farmacia->id],
            [
                'nombre' => 'Rubén Repartidor', 'correo' => 'repartidor@logistica.test', 'rol' => Rol::Repartidor,
                'zona_id' => $centro->id, 'disponibilidad' => Disponibilidad::Disponible,
            ],
        ];

        foreach ($usuarios as $datos) {
            Usuario::query()->firstOrCreate(
                ['correo' => $datos['correo']],
                [...$datos, 'password' => 'password', 'activo' => true],
            );
        }
    }
}
