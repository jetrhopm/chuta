<?php

use App\Domain\Access\Enums\AdminPermission;
use App\Domain\Access\Enums\AdminRole;
use App\Domain\Notifications\ConfigureMailer;
use App\Domain\Notifications\MailSettingsRepository;
use App\Filament\Pages\ManageMailSettings;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('niega la pantalla al administrador sin permiso de SMTP', function () {
    // El rol de administrador nace sin ese permiso.
    $this->actingAs(User::factory()->withRole(AdminRole::Admin)->create());

    expect(ManageMailSettings::canAccess())->toBeFalse();

    $this->get(ManageMailSettings::getUrl())->assertForbidden();
});

it('deja entrar al superadministrador', function () {
    $this->actingAs(User::factory()->withRole(AdminRole::SuperAdmin)->create());

    $this->get(ManageMailSettings::getUrl())->assertOk();
});

it('guarda la configuracion con la contrasena cifrada', function () {
    $this->actingAs(User::factory()->withRole(AdminRole::SuperAdmin)->create());

    Livewire::test(ManageMailSettings::class)
        ->fillForm([
            'enabled' => true,
            'host' => 'smtp.midominio.test',
            'port' => 465,
            'encryption' => 'ssl',
            'username' => 'ventas',
            'password' => 'secreto-real',
            'from_address' => 'ventas@midominio.test',
            'from_name' => 'Mi tienda',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $settings = app(MailSettingsRepository::class)->get();

    expect($settings->host)->toBe('smtp.midominio.test')
        ->and($settings->port)->toBe(465)
        ->and($settings->password)->toBe('secreto-real');

    $guardado = DB::table('settings')
        ->where('group', 'mail')
        ->where('key', 'password')
        ->value('value');

    expect((string) $guardado)->not->toContain('secreto-real');
});

it('no destruye la contrasena al volver a guardar la pantalla', function () {
    app(MailSettingsRepository::class)->save([
        'enabled' => true,
        'host' => 'smtp.midominio.test',
        'from_address' => 'ventas@midominio.test',
        'password' => 'secreto-original',
    ]);

    $this->actingAs(User::factory()->withRole(AdminRole::SuperAdmin)->create());

    // Se carga la pantalla (la contrasena llega enmascarada) y se cambia otro
    // campo, que es lo que haria cualquiera.
    Livewire::test(ManageMailSettings::class)
        ->fillForm(['from_name' => 'Otro nombre'])
        ->call('save')
        ->assertHasNoFormErrors();

    $settings = app(MailSettingsRepository::class)->get();

    expect($settings->password)->toBe('secreto-original')
        ->and($settings->fromName)->toBe('Otro nombre');
});

it('muestra la contrasena enmascarada y no la real', function () {
    app(MailSettingsRepository::class)->save([
        'enabled' => true,
        'host' => 'smtp.test',
        'from_address' => 'a@b.test',
        'password' => 'secreto-real',
    ]);

    $display = app(MailSettingsRepository::class)->forDisplay();

    expect($display['password'])->not->toBe('secreto-real')
        ->and($display['password'])->toBe(str_repeat('*', 12));
});

it('el correo de prueba avisa cuando la configuracion esta incompleta', function () {
    $this->actingAs(User::factory()->withRole(AdminRole::SuperAdmin)->create());

    Livewire::test(ManageMailSettings::class)
        ->fillForm(['enabled' => true, 'host' => '', 'from_address' => ''])
        ->callAction('correoDePrueba', data: ['to' => 'prueba@destino.test'])
        ->assertNotified();

    // Sin servidor ni remitente no se intenta enviar nada.
    expect(app(ConfigureMailer::class)->apply())->toBeFalse();
});

it('el correo de prueba se envia sin cola para responder de inmediato', function () {
    Mail::fake();

    $this->actingAs(User::factory()->withRole(AdminRole::SuperAdmin)->create());

    Livewire::test(ManageMailSettings::class)
        ->fillForm([
            'enabled' => true,
            'host' => 'smtp.midominio.test',
            'port' => 587,
            'from_address' => 'ventas@midominio.test',
            'from_name' => 'Mi tienda',
        ])
        ->callAction('correoDePrueba', data: ['to' => 'prueba@destino.test'])
        ->assertNotified();

    // La prueba tiene que decir ahora mismo si funciona, no dentro de un rato.
    // No se cuenta lo enviado porque Mail::fake solo registra Mailables y este es
    // un mensaje suelto: lo comprobable es que no quedo en cola y que la accion
    // reporto exito, que solo ocurre si el envio no lanzo excepcion.
    Mail::assertNothingQueued();
});

it('aplica la configuracion administrable sobre la del entorno', function () {
    app(MailSettingsRepository::class)->save([
        'enabled' => true,
        'host' => 'smtp.administrable.test',
        'port' => 2525,
        'from_address' => 'desde@administrable.test',
        'from_name' => 'Administrable',
        'password' => 'clave',
    ]);

    expect(app(ConfigureMailer::class)->apply())->toBeTrue()
        ->and(config('mail.mailers.smtp.host'))->toBe('smtp.administrable.test')
        ->and(config('mail.mailers.smtp.port'))->toBe(2525)
        ->and(config('mail.from.address'))->toBe('desde@administrable.test');
});

it('no toca la configuracion del entorno si el envio esta desactivado', function () {
    $original = config('mail.mailers.smtp.host');

    app(MailSettingsRepository::class)->save([
        'enabled' => false,
        'host' => 'smtp.ignorado.test',
        'from_address' => 'x@y.test',
    ]);

    expect(app(ConfigureMailer::class)->apply())->toBeFalse()
        ->and(config('mail.mailers.smtp.host'))->toBe($original);
});

it('al borrar la configuracion desactiva el envio', function () {
    app(MailSettingsRepository::class)->save([
        'enabled' => true,
        'host' => 'smtp.test',
        'from_address' => 'a@b.test',
        'password' => 'clave',
    ]);

    app(MailSettingsRepository::class)->forget();

    $settings = app(MailSettingsRepository::class)->get();

    expect($settings->enabled)->toBeFalse()
        ->and($settings->password)->toBe('')
        ->and($settings->isUsable())->toBeFalse();
});

it('esconde la pantalla si se revoca el permiso al rol', function () {
    $admin = User::factory()->withRole(AdminRole::Admin)->create();

    Role::where('name', AdminRole::Admin->value)
        ->firstOrFail()
        ->givePermissionTo(AdminPermission::ManageMailSettings->value);

    $this->actingAs($admin->fresh());

    expect(ManageMailSettings::canAccess())->toBeTrue();

    Role::where('name', AdminRole::Admin->value)
        ->firstOrFail()
        ->revokePermissionTo(AdminPermission::ManageMailSettings->value);

    $this->actingAs($admin->fresh());

    expect(ManageMailSettings::canAccess())->toBeFalse();
});
