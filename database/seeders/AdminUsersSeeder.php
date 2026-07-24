<?php

namespace Database\Seeders;

use App\Domain\Access\Enums\AdminRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Cuentas administrativas iniciales.
 *
 * Las contrasenas son deliberadamente triviales porque solo sirven para el
 * entorno local. En produccion las cuentas quedan marcadas para exigir cambio
 * de contrasena en el primer acceso.
 */
class AdminUsersSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            [
                'name' => 'Superadministrador',
                'email' => 'superadmin@local.test',
                'role' => AdminRole::SuperAdmin,
            ],
            [
                'name' => 'Administrador',
                'email' => 'admin@local.test',
                'role' => AdminRole::Admin,
            ],
        ];

        foreach ($accounts as $account) {
            // firstOrCreate y no updateOrCreate: si la cuenta ya existe se
            // respeta su contrasena y sus datos. Volver a sembrar no debe
            // devolver una contrasena conocida a una cuenta en uso.
            $user = User::firstOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                    'is_active' => true,
                    'must_change_password' => ! app()->environment('local', 'testing'),
                ],
            );

            // assignRole es idempotente, pero se comprueba antes para no escribir
            // en la tabla pivote en cada ejecucion.
            if (! $user->hasRole($account['role']->value)) {
                $user->assignRole($account['role']->value);
            }
        }
    }
}
