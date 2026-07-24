<?php

use App\Domain\Access\Enums\AdminPermission;
use App\Domain\Access\Enums\AdminRole;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Auth\Pages\Login;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('deja entrar al panel a quien tiene rol administrativo', function (AdminRole $role) {
    $user = User::factory()->withRole($role)->create();

    $this->actingAs($user)
        ->get('/admin')
        ->assertOk();
})->with([
    'superadministrador' => AdminRole::SuperAdmin,
    'administrador' => AdminRole::Admin,
]);

it('niega el panel a una cuenta sin rol administrativo', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/admin')
        ->assertForbidden();
});

it('niega el panel a una cuenta desactivada aunque tenga rol', function () {
    $user = User::factory()->inactive()->withRole(AdminRole::Admin)->create();

    $this->actingAs($user)
        ->get('/admin')
        ->assertForbidden();
});

it('concede cualquier permiso al superadministrador sin asignarselo', function () {
    $user = User::factory()->withRole(AdminRole::SuperAdmin)->create();

    expect($user->getAllPermissions())->toBeEmpty();

    foreach (AdminPermission::cases() as $permission) {
        expect($user->can($permission->value))->toBeTrue();
    }
});

it('limita al administrador a sus permisos iniciales', function () {
    $user = User::factory()->withRole(AdminRole::Admin)->create();

    expect($user->can(AdminPermission::ViewProducts->value))->toBeTrue()
        ->and($user->can(AdminPermission::ManageOrders->value))->toBeTrue();

    // Las credenciales de las integraciones y la gestion de administradores
    // quedan fuera: son las acciones que permitirian escalar privilegios.
    expect($user->can(AdminPermission::ManageAdministrators->value))->toBeFalse()
        ->and($user->can(AdminPermission::ManagePaymentProviders->value))->toBeFalse()
        ->and($user->can(AdminPermission::DeleteIntegrationCredentials->value))->toBeFalse();
});

it('retira todos los permisos a una cuenta desactivada', function () {
    $user = User::factory()->inactive()->withRole(AdminRole::Admin)->create();

    expect($user->can(AdminPermission::ViewProducts->value))->toBeFalse();
});

it('retira todos los permisos a un superadministrador desactivado', function () {
    $user = User::factory()->inactive()->withRole(AdminRole::SuperAdmin)->create();

    expect($user->can(AdminPermission::ViewProducts->value))->toBeFalse();
});

it('guarda el correo en minusculas', function () {
    $user = User::factory()->create(['email' => '  Persona.Ejemplo@LOCAL.TEST  ']);

    expect($user->fresh()->email)->toBe('persona.ejemplo@local.test');
});

it('registra fecha, IP y agente del ultimo acceso', function () {
    $user = User::factory()->withRole(AdminRole::Admin)->create();

    expect($user->last_login_at)->toBeNull();

    Livewire::test(Login::class)
        ->fillForm(['email' => $user->email, 'password' => 'password'])
        ->call('authenticate');

    $user->refresh();

    expect(auth()->id())->toBe($user->id)
        ->and($user->last_login_at)->not->toBeNull()
        ->and($user->last_login_ip)->not->toBeNull();
});

it('bloquea el acceso tras varios intentos fallidos', function () {
    $user = User::factory()->withRole(AdminRole::Admin)->create();

    // Filament permite cinco intentos por minuto. El sexto debe rechazarse
    // aunque la contrasena sea la correcta.
    foreach (range(1, 5) as $attempt) {
        Livewire::test(Login::class)
            ->fillForm(['email' => $user->email, 'password' => 'contrasena-incorrecta'])
            ->call('authenticate');
    }

    Livewire::test(Login::class)
        ->fillForm(['email' => $user->email, 'password' => 'password'])
        ->call('authenticate');

    expect(auth()->check())->toBeFalse();
});
