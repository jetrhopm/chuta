<?php

use App\Domain\Access\Enums\AdminPermission;
use App\Domain\Access\Enums\AdminRole;
use App\Domain\Meta\MetaPixelSettings;
use App\Domain\Meta\MetaPixelSettingsRepository;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Filament\Pages\ManageMetaAdsSettings;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function activarPixel(string $pixelId = '1234567890'): void
{
    app(MetaPixelSettingsRepository::class)->save(new MetaPixelSettings(
        enabled: true,
        pixelId: $pixelId,
    ));
}

it('guarda la configuracion desde el panel', function () {
    $this->actingAs(User::factory()->withRole(AdminRole::SuperAdmin)->create());

    Livewire::test(ManageMetaAdsSettings::class)
        ->fillForm([
            'enabled' => true,
            'pixel_id' => '1234567890',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $settings = app(MetaPixelSettingsRepository::class)->get();

    expect($settings->canTrack())->toBeTrue()
        ->and($settings->pixelId)->toBe('1234567890');
});

it('exige pixel id si se activa', function () {
    $this->actingAs(User::factory()->withRole(AdminRole::SuperAdmin)->create());

    Livewire::test(ManageMetaAdsSettings::class)
        ->fillForm([
            'enabled' => true,
            'pixel_id' => '',
        ])
        ->call('save')
        ->assertHasFormErrors(['pixel_id']);
});

it('niega el panel a quien no gestiona meta ads', function () {
    $admin = User::factory()->withRole(AdminRole::Admin)->create();

    Role::where('name', AdminRole::Admin->value)
        ->firstOrFail()
        ->revokePermissionTo(AdminPermission::ManageMetaAds->value);

    $this->actingAs($admin->fresh());

    expect(ManageMetaAdsSettings::canAccess())->toBeFalse();
});

it('no inyecta el pixel cuando esta desactivado', function () {
    $this->get('/')
        ->assertOk()
        ->assertDontSee('connect.facebook.net/en_US/fbevents.js', escape: false)
        ->assertSee('window.chutamaxMetaTrack = function () {};', escape: false);
});

it('inyecta pageview y eventos de carrito en la portada', function () {
    activarPixel();
    Product::factory()->featured()->withStock(5)->create();

    $this->get('/')
        ->assertOk()
        ->assertSee("fbq('init'", escape: false)
        ->assertSee("fbq('track', 'PageView')", escape: false)
        ->assertSee("'AddToCart'", escape: false)
        ->assertSee("'InitiateCheckout'", escape: false);
});

it('inyecta viewcontent en la ficha del producto', function () {
    activarPixel();
    $product = Product::factory()->withStock(5)->create(['name' => 'Proteina Meta']);

    $this->get(route('products.show', ['slug' => $product->slug]))
        ->assertOk()
        ->assertSee("'ViewContent'", escape: false)
        ->assertSee('Proteina Meta');
});

it('solo envia purchase cuando el pago esta aprobado', function () {
    activarPixel();
    $product = Product::factory()->withStock(5)->create(['price_cents' => 50000]);

    enviarCheckout([[$product, 1]])->assertRedirect();

    $order = Order::firstOrFail();

    $this->get($order->trackingUrl())
        ->assertOk()
        ->assertDontSee("'Purchase'", escape: false);

    $order->forceFill(['payment_status' => PaymentStatus::Approved])->save();

    $this->get($order->fresh()->trackingUrl())
        ->assertOk()
        ->assertSee("'Purchase'", escape: false)
        ->assertSee($order->code);
});
