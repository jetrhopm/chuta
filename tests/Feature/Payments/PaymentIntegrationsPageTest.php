<?php

use App\Domain\Access\Enums\AdminPermission;
use App\Domain\Access\Enums\AdminRole;
use App\Domain\Payments\Enums\PaymentProvider;
use App\Domain\Payments\Settings\GatewaySettings;
use App\Filament\Pages\ManagePaymentIntegrations;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('niega la pantalla al administrador sin permiso de proveedores de pago', function () {
    // El rol de administrador nace sin ese permiso: las credenciales de las
    // integraciones son justo lo que queda fuera de su alcance.
    $this->actingAs(User::factory()->withRole(AdminRole::Admin)->create());

    expect(ManagePaymentIntegrations::canAccess())->toBeFalse();

    $this->get(ManagePaymentIntegrations::getUrl())->assertForbidden();
});

it('deja entrar al superadministrador', function () {
    $this->actingAs(User::factory()->withRole(AdminRole::SuperAdmin)->create());

    $this->get(ManagePaymentIntegrations::getUrl())->assertOk();
});

it('guarda las credenciales cifradas', function () {
    $this->actingAs(User::factory()->withRole(AdminRole::SuperAdmin)->create());

    Livewire::test(ManagePaymentIntegrations::class)
        ->fillForm([
            'clip.enabled' => true,
            'clip.sandbox' => true,
            'clip.api_key' => 'llave-nueva',
            'clip.secret_key' => 'secreto-nuevo',
            'clip.webhook_secret' => 'secreto-webhook',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $settings = app(GatewaySettings::class);

    expect($settings->get(PaymentProvider::Clip, 'api_key'))->toBe('llave-nueva')
        ->and($settings->isEnabled(PaymentProvider::Clip))->toBeTrue();

    $guardado = DB::table('settings')
        ->where('group', 'payments.clip')
        ->where('key', 'api_key')
        ->value('value');

    expect((string) $guardado)->not->toContain('llave-nueva');
});

it('no destruye la credencial guardada al volver a guardar la pantalla', function () {
    app(GatewaySettings::class)->save(PaymentProvider::Clip, [
        'enabled' => true,
        'api_key' => 'llave-original',
        'secret_key' => 'secreto-original',
    ]);

    $this->actingAs(User::factory()->withRole(AdminRole::SuperAdmin)->create());

    // Se carga la pantalla (los secretos llegan enmascarados) y se guarda sin
    // tocarlos, que es lo que haria cualquiera al cambiar otro campo.
    Livewire::test(ManagePaymentIntegrations::class)
        ->fillForm(['clip.sandbox' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    $settings = app(GatewaySettings::class);

    expect($settings->get(PaymentProvider::Clip, 'api_key'))->toBe('llave-original')
        ->and($settings->get(PaymentProvider::Clip, 'secret_key'))->toBe('secreto-original')
        ->and($settings->isSandbox(PaymentProvider::Clip))->toBeFalse();
});

it('la prueba de conexion informa el resultado sin generar cobros', function () {
    configurarClip();

    Http::fake(['*' => Http::response(['error' => 'not_found'], 404)]);

    $this->actingAs(User::factory()->withRole(AdminRole::SuperAdmin)->create());

    Livewire::test(ManagePaymentIntegrations::class)
        ->callAction('probarConexion', data: ['provider' => 'clip'])
        ->assertNotified();

    // Solo lecturas: ni una peticion que pudiera cobrar.
    Http::assertSentCount(1);
    Http::assertSent(fn ($request): bool => $request->method() === 'GET');
});

it('la prueba de conexion avisa cuando el proveedor no tiene adaptador', function () {
    $this->actingAs(User::factory()->withRole(AdminRole::SuperAdmin)->create());

    Livewire::test(ManagePaymentIntegrations::class)
        ->callAction('probarConexion', data: ['provider' => 'paypal'])
        ->assertNotified();
});

it('borra las credenciales y desactiva el metodo', function () {
    configurarClip();

    $this->actingAs(User::factory()->withRole(AdminRole::SuperAdmin)->create());

    Livewire::test(ManagePaymentIntegrations::class)
        ->callAction('borrarCredenciales', data: ['provider' => 'clip'])
        ->assertNotified();

    $settings = app(GatewaySettings::class);

    // Se desactiva primero: dejarlo activo sin credenciales lo ofreceria en el
    // checkout para fallar despues.
    expect($settings->isEnabled(PaymentProvider::Clip))->toBeFalse()
        ->and($settings->hasSecret(PaymentProvider::Clip, 'api_key'))->toBeFalse()
        ->and($settings->hasSecret(PaymentProvider::Clip, 'secret_key'))->toBeFalse();
});

it('esconde el borrado a quien no tiene el permiso especial', function () {
    $superadmin = User::factory()->withRole(AdminRole::SuperAdmin)->create();

    // Se prueba con un administrador al que se le concede el permiso de gestionar
    // proveedores pero no el de borrar credenciales, que van aparte a proposito.
    $admin = User::factory()->withRole(AdminRole::Admin)->create();

    Role::where('name', AdminRole::Admin->value)
        ->firstOrFail()
        ->givePermissionTo(AdminPermission::ManagePaymentProviders->value);

    $this->actingAs($admin->fresh());

    Livewire::test(ManagePaymentIntegrations::class)
        ->assertActionVisible('probarConexion')
        ->assertActionHidden('borrarCredenciales');

    // El superadministrador si lo ve.
    $this->actingAs($superadmin);

    Livewire::test(ManagePaymentIntegrations::class)
        ->assertActionVisible('borrarCredenciales');
});

it('ofrece la direccion del webhook para registrarla en el proveedor', function () {
    $this->actingAs(User::factory()->withRole(AdminRole::SuperAdmin)->create());

    // Sin esta direccion a la vista, configurar Clip obliga a construirla a mano.
    Livewire::test(ManagePaymentIntegrations::class)
        ->assertFormSet([
            'clip.webhook_url' => route('payments.webhook', ['provider' => 'clip']),
        ]);
});
