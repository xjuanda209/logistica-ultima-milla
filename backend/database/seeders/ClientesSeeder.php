<?php

namespace Database\Seeders;

use App\Modules\Administracion\Models\Comercio;
use App\Modules\Administracion\Models\Zona;
use App\Modules\Pedidos\Models\Cliente;
use Illuminate\Database\Seeder;

/**
 * 10 clientes ficticios: 5 por comercio.
 */
class ClientesSeeder extends Seeder
{
    public function run(): void
    {
        $comercios = Comercio::query()->orderBy('id')->get();
        $zonas = Zona::query()->orderBy('id')->get();

        $clientes = [
            ['Laura Gómez', '1000000001', '3001110001', 'Calle 12 # 6-30', 'San José'],
            ['Carlos Pérez', '1000000002', '3001110002', 'Carrera 8 # 21-15', 'La Pradera'],
            ['María Rodríguez', '1000000003', '3001110003', 'Calle 30 # 14-08', 'El Jardín'],
            ['Andrés Martínez', '1000000004', '3001110004', 'Carrera 3 # 45-60', 'Villa Verde'],
            ['Sofía Hernández', '1000000005', '3001110005', 'Calle 50 # 9-11', 'Los Álamos'],
            ['Julián Ramírez', '1000000006', '3001110006', 'Carrera 20 # 33-27', 'San José'],
            ['Valentina Torres', '1000000007', '3001110007', 'Calle 18 # 2-44', 'La Pradera'],
            ['Diego Castaño', '1000000008', '3001110008', 'Carrera 11 # 60-05', 'El Jardín'],
            ['Paula Ospina', '1000000009', '3001110009', 'Calle 72 # 16-90', 'Villa Verde'],
            ['Mateo Londoño', '1000000010', '3001110010', 'Carrera 27 # 10-33', 'Los Álamos'],
        ];

        foreach ($clientes as $indice => [$nombre, $cedula, $telefono, $direccion, $barrio]) {
            $comercio = $comercios[intdiv($indice, 5) % $comercios->count()];

            Cliente::query()->firstOrCreate(
                ['comercio_id' => $comercio->id, 'cedula' => $cedula],
                [
                    'nombre' => $nombre,
                    'telefono' => $telefono,
                    'correo' => 'cliente'.($indice + 1).'@correo.test',
                    'direccion' => $direccion,
                    'barrio' => $barrio,
                    'indicaciones' => $indice % 3 === 0 ? 'Llamar al llegar' : null,
                    'zona_id' => $zonas[$indice % $zonas->count()]->id,
                ],
            );
        }
    }
}
