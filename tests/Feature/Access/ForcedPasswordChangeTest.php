<?php

use App\Domain\Access\Enums\AdminRole;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('manda al perfil mientras la contrasena inicial siga sin cambiarse', function () {
    $user = User::factory()->mustChangePassword()->withRole(AdminRole::Admin)->create();

    $this->actingAs($user)
        ->get('/admin')
        ->assertRedirect(Filament::getPanel('admin')->getProfileUrl());
});

it('no interfiere con la propia pagina de perfil', function () {
    $user = User::factory()->mustChangePassword()->withRole(AdminRole::Admin)->create();

    // Si el perfil tambien redirigiera, la persona quedaria en un bucle sin
    // forma de cambiar la contrasena.
    $this->actingAs($user)
        ->get(Filament::getPanel('admin')->getProfileUrl())
        ->assertOk();
});

it('deja pasar a quien ya cambio la contrasena', function () {
    $user = User::factory()->withRole(AdminRole::Admin)->create();

    $this->actingAs($user)
        ->get('/admin')
        ->assertOk();
});
