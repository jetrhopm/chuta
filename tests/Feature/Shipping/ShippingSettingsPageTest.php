<?php

use App\Domain\Access\Enums\AdminPermission;
use App\Domain\Access\Enums\AdminRole;
use App\Domain\Shipping\ShippingSettingsRepository;
use App\Filament\Pages\ManageShippingSettings;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\ShippingSettingsSeeder;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed([RolesAndPermissionsSeeder::class, ShippingSettingsSeeder::class]);
});

it('carga la configuracion actual en el formulario', function () {
    $this->actingAs(User::factory()->withRole(AdminRole::Admin)->create());

    Livewire::test(ManageShippingSettings::class)
        ->assertOk()
        ->assertFormSet([
            'flat_cents' => 9900,
            'free_shipping_threshold_cents' => 80000,
        ]);
});

it('guarda los cambios y la tienda los usa de inmediato', function () {
    $this->actingAs(User::factory()->withRole(AdminRole::Admin)->create());

    Livewire::test(ManageShippingSettings::class)
        ->fillForm([
            'flat_cents' => 13500,
            'free_shipping_threshold_cents' => 120000,
            'excluded_states' => "Michoacan\nGuerrero",
            'excluded_postcodes' => '85000',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $settings = app(ShippingSettingsRepository::class)->get();

    expect($settings->flatCents)->toBe(13500)
        ->and($settings->freeShippingThresholdCents)->toBe(120000)
        ->and($settings->excludedStates)->toBe(['Michoacan', 'Guerrero'])
        ->and($settings->excludedPostcodes)->toBe(['85000']);
});

it('exige una tarifa valida', function () {
    $this->actingAs(User::factory()->withRole(AdminRole::Admin)->create());

    Livewire::test(ManageShippingSettings::class)
        ->fillForm(['flat_cents' => null])
        ->call('save')
        ->assertHasFormErrors(['flat_cents']);
});

it('niega la pantalla a quien no puede administrar pedidos', function () {
    $admin = User::factory()->withRole(AdminRole::Admin)->create();

    Role::where('name', AdminRole::Admin->value)
        ->firstOrFail()
        ->revokePermissionTo(AdminPermission::ManageOrders->value);

    $this->actingAs($admin->fresh());

    expect(ManageShippingSettings::canAccess())->toBeFalse();

    $this->get(ManageShippingSettings::getUrl())->assertForbidden();
});

it('deja entrar al superadministrador', function () {
    $this->actingAs(User::factory()->withRole(AdminRole::SuperAdmin)->create());

    $this->get(ManageShippingSettings::getUrl())->assertOk();
});
