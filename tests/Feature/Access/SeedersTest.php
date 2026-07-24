<?php

use App\Domain\Access\Enums\AdminPermission;
use App\Domain\Access\Enums\AdminRole;
use App\Models\User;
use Database\Seeders\AdminUsersSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

it('crea las cuentas y los roles iniciales', function () {
    $this->seed([RolesAndPermissionsSeeder::class, AdminUsersSeeder::class]);

    expect(Permission::count())->toBe(count(AdminPermission::cases()))
        ->and(Role::count())->toBe(count(AdminRole::cases()));

    $superadmin = User::where('email', 'superadmin@local.test')->first();
    $admin = User::where('email', 'admin@local.test')->first();

    expect($superadmin)->not->toBeNull()
        ->and($superadmin->hasRole(AdminRole::SuperAdmin->value))->toBeTrue()
        ->and($admin)->not->toBeNull()
        ->and($admin->hasRole(AdminRole::Admin->value))->toBeTrue();
});

it('no duplica nada al ejecutarse varias veces', function () {
    $this->seed([RolesAndPermissionsSeeder::class, AdminUsersSeeder::class]);
    $this->seed([RolesAndPermissionsSeeder::class, AdminUsersSeeder::class]);
    $this->seed([RolesAndPermissionsSeeder::class, AdminUsersSeeder::class]);

    expect(User::count())->toBe(2)
        ->and(Role::count())->toBe(count(AdminRole::cases()))
        ->and(Permission::count())->toBe(count(AdminPermission::cases()))
        ->and(User::where('email', 'admin@local.test')->first()->roles)->toHaveCount(1);
});

it('respeta la contrasena de una cuenta que ya existe', function () {
    $this->seed([RolesAndPermissionsSeeder::class, AdminUsersSeeder::class]);

    $admin = User::where('email', 'admin@local.test')->first();
    $admin->forceFill(['password' => Hash::make('una-contrasena-propia')])->save();

    $this->seed(AdminUsersSeeder::class);

    // Volver a sembrar no debe devolver una contrasena conocida a una cuenta
    // que ya esta en uso.
    expect(Hash::check('una-contrasena-propia', $admin->fresh()->password))->toBeTrue();
});

it('conserva los permisos que el superadministrador ajusto en un rol', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $role = Role::where('name', AdminRole::Admin->value)->first();
    $role->revokePermissionTo(AdminPermission::ViewProducts->value);

    $this->seed(RolesAndPermissionsSeeder::class);

    expect($role->fresh()->hasPermissionTo(AdminPermission::ViewProducts->value))->toBeFalse();
});

it('registra en el catalogo cada permiso nuevo del enum', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $registrados = Permission::pluck('name')->sort()->values()->all();
    $esperados = collect(AdminPermission::values())->sort()->values()->all();

    expect($registrados)->toBe($esperados);
});
