<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Datos ficticios de arranque. Todos los seeders son idempotentes:
 * si se ejecutan dos veces no duplican filas.
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            CatalogosSeeder::class,
            UsuariosSeeder::class,
            ClientesSeeder::class,
            PedidosSeeder::class,
        ]);
    }
}
