<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Todos los seeders son idempotentes: ejecutarlos varias veces deja la base
     * en el mismo estado y no duplica registros.
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            AdminUsersSeeder::class,
        ]);
    }
}
