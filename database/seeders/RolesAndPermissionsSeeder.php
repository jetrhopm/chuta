<?php

namespace Database\Seeders;

use App\Domain\Access\Enums\AdminPermission;
use App\Domain\Access\Enums\AdminRole;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // El paquete cachea el mapa de permisos; sin limpiarlo, lo que se cree
        // aqui no se veria hasta la siguiente peticion.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (AdminPermission::cases() as $permission) {
            Permission::findOrCreate($permission->value, 'web');
        }

        foreach (AdminRole::cases() as $role) {
            $existed = Role::where('name', $role->value)->where('guard_name', 'web')->exists();

            $model = Role::findOrCreate($role->value, 'web');

            // Los permisos por defecto solo se aplican al crear el rol. Volver a
            // sembrar no debe deshacer los ajustes que el superadministrador
            // haya hecho desde el panel.
            if (! $existed) {
                $model->syncPermissions($role->defaultPermissionValues());
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
