<?php

use App\Domain\Shipping\Data\ShippingSettings;
use App\Domain\Shipping\ShippingSettingsRepository;
use App\Models\Order;
use App\Models\Product;
use Database\Seeders\ShippingSettingsSeeder;

beforeEach(function () {
    $this->seed(ShippingSettingsSeeder::class);
});

it('cobra la tarifa configurada en el panel y no la del entorno', function () {
    // Se cambia solo la configuracion administrable. Si el checkout siguiera
    // leyendo el archivo de entorno, cobraria los $99 originales.
    app(ShippingSettingsRepository::class)->save(new ShippingSettings(flatCents: 15000));

    $product = Product::factory()->withStock(10)->create(['price_cents' => 20000]);

    enviarCheckout([[$product, 1]])->assertSessionHasNoErrors();

    $order = Order::firstOrFail();

    expect($order->shipping_cents)->toBe(15000)
        ->and($order->total_cents)->toBe(35000);
});

it('aplica el envio gratis del umbral configurado', function () {
    app(ShippingSettingsRepository::class)->save(new ShippingSettings(
        flatCents: 9900,
        freeShippingThresholdCents: 50000,
    ));

    $product = Product::factory()->withStock(10)->create(['price_cents' => 50000]);

    enviarCheckout([[$product, 1]])->assertSessionHasNoErrors();

    $order = Order::firstOrFail();

    expect($order->shipping_cents)->toBe(0)
        ->and($order->total_cents)->toBe(50000);
});

it('ignora un costo de envio enviado desde el navegador', function () {
    $product = Product::factory()->withStock(10)->create(['price_cents' => 20000]);

    // Aunque el formulario traiga un envio de cero, el servidor recalcula.
    enviarCheckout([[$product, 1]], ['shipping_cents' => 0, 'total_cents' => 20000])
        ->assertSessionHasNoErrors();

    $order = Order::firstOrFail();

    expect($order->shipping_cents)->toBe(9900)
        ->and($order->total_cents)->toBe(29900);
});

it('rechaza el pedido a un estado sin cobertura', function () {
    app(ShippingSettingsRepository::class)->save(new ShippingSettings(
        excludedStates: ['Michoacan'],
    ));

    $product = Product::factory()->withStock(10)->create();

    enviarCheckout([[$product, 1]], ['shipping_state' => 'Michoacán'])
        ->assertSessionHasErrors('shipping_postcode');

    // Sin pedido y sin descuento de inventario: el intento no deja rastro.
    expect(Order::count())->toBe(0)
        ->and($product->fresh()->stock)->toBe(10);
});

it('rechaza el pedido a un codigo postal sin cobertura', function () {
    app(ShippingSettingsRepository::class)->save(new ShippingSettings(
        excludedPostcodes: ['85000'],
    ));

    $product = Product::factory()->withStock(10)->create();

    enviarCheckout([[$product, 1]], ['shipping_postcode' => '85000'])
        ->assertSessionHasErrors('shipping_postcode');

    expect(Order::count())->toBe(0);
});

it('rechaza el pedido cuando los envios estan desactivados', function () {
    app(ShippingSettingsRepository::class)->save(new ShippingSettings(enabled: false));

    $product = Product::factory()->withStock(10)->create();

    enviarCheckout([[$product, 1]])->assertSessionHasErrors('shipping_postcode');

    expect(Order::count())->toBe(0);
});

it('explica la falta de cobertura sin tecnicismos', function () {
    app(ShippingSettingsRepository::class)->save(new ShippingSettings(
        excludedStates: ['Sonora'],
    ));

    enviarCheckout([[Product::factory()->withStock(5)->create(), 1]]);

    $mensaje = session('errors')->get('shipping_postcode')[0];

    expect($mensaje)->toContain('Todavia no llegamos a ese estado')
        ->and($mensaje)->not->toContain('Exception')
        ->and($mensaje)->not->toContain('SQL');
});

it('la tienda publica la configuracion de envios que ve el cliente', function () {
    app(ShippingSettingsRepository::class)->save(new ShippingSettings(
        flatCents: 12300,
        freeShippingThresholdCents: 99900,
    ));

    $this->get('/')
        ->assertOk()
        // El adelanto que muestra el carrito sale de la configuracion, no de
        // numeros escritos en la plantilla.
        ->assertSee('12300', escape: false)
        ->assertSee('99900', escape: false);
});
