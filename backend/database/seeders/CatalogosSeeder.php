<?php

namespace Database\Seeders;

use App\Modules\Administracion\Models\Comercio;
use App\Modules\Administracion\Models\Configuracion;
use App\Modules\Administracion\Models\Zona;
use App\Modules\Incidencias\Models\TipoIncidencia;
use Illuminate\Database\Seeder;

/**
 * Zonas, comercios, tipos de incidencia y la fila única de configuración.
 */
class CatalogosSeeder extends Seeder
{
    public function run(): void
    {
        $zonas = [
            'Centro' => 'Zona céntrica y comercial',
            'Norte' => 'Barrios del norte de la ciudad',
            'Sur' => 'Barrios del sur de la ciudad',
            'Occidente' => 'Barrios del occidente de la ciudad',
        ];

        foreach ($zonas as $nombre => $descripcion) {
            Zona::query()->updateOrCreate(['nombre' => $nombre], ['descripcion' => $descripcion]);
        }

        $comercios = [
            'Tienda La Esquina' => 'Calle 10 # 5-20, Centro',
            'Farmacia Vida Sana' => 'Carrera 15 # 40-12, Norte',
        ];

        foreach ($comercios as $nombre => $direccion) {
            Comercio::query()->firstOrCreate(['nombre' => $nombre], ['direccion_recogida' => $direccion]);
        }

        $tipos = ['Cliente ausente', 'Dirección incorrecta', 'Paquete dañado', 'Cliente rechaza el pedido', 'Otro'];

        foreach ($tipos as $nombre) {
            TipoIncidencia::query()->firstOrCreate(['nombre' => $nombre]);
        }

        Configuracion::query()->firstOrCreate(['id' => 1], ['max_pedidos_activos' => 3]);
    }
}
