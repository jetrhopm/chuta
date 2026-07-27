<?php

use App\Domain\Shipping\Data\ShippingSettings;
use App\Domain\Shipping\ShippingSettingsRepository;
use App\Models\CouponUsage;
use App\Models\Order;
use App\Models\Product;
use App\Models\Promotion;
use Database\Seeders\ShippingSettingsSeeder;

beforeEach(function () {
    $this->seed(ShippingSettingsSeeder::class);
});

it('guarda el pedido con el descuento aplicado', function () {
    Promotion::factory()->percentage(10)->create(['name' => 'Diez por ciento']);

    $product = Product::factory()->withStock(10)->create(['price_cents' => 50000]);

    enviarCheckout([[$product, 2]])->assertSessionHasNoErrors();

    $order = Order::firstOrFail();

    // Subtotal 1000, descuento 100. Los 900 restantes siguen superando el umbral
    // de 800, asi que el envio sale gratis y el total queda en 900.
    expect($order->subtotal_cents)->toBe(100000)
        ->and($order->discount_cents)->toBe(10000)
        ->and($order->shipping_cents)->toBe(0)
        ->and($order->total_cents)->toBe(90000);
});

it('ignora un descuento enviado desde el navegador', function () {
    $product = Product::factory()->withStock(10)->create(['price_cents' => 50000]);

    // No hay ninguna promocion activa, asi que el descuento debe ser cero por mas
    // que el formulario diga lo contrario.
    enviarCheckout([[$product, 1]], ['discount_cents' => 40000, 'total_cents' => 10000])
        ->assertSessionHasNoErrors();

    $order = Order::firstOrFail();

    expect($order->discount_cents)->toBe(0)
        ->and($order->total_cents)->toBe(59900);
});

it('aplica el cupon que captura el cliente', function () {
    Promotion::factory()->coupon('AHORRA20')->percentage(20)->create();

    $product = Product::factory()->withStock(10)->create(['price_cents' => 50000]);

    enviarCheckout([[$product, 1]], ['coupon_code' => 'ahorra20'])->assertSessionHasNoErrors();

    $order = Order::firstOrFail();

    expect($order->discount_cents)->toBe(10000);
});

it('guarda la fotografia del descuento con el pedido', function () {
    Promotion::factory()->coupon('AHORRA20')->percentage(20)->create([
        'name' => 'Veinte por ciento',
        'description' => 'Promocion de bienvenida',
    ]);

    $product = Product::factory()->withStock(10)->create(['price_cents' => 50000]);

    enviarCheckout([[$product, 1]], ['coupon_code' => 'AHORRA20']);

    $order = Order::firstOrFail();

    expect($order->discount_breakdown)->toHaveCount(1)
        ->and($order->discount_breakdown[0]['name'])->toBe('Veinte por ciento')
        ->and($order->discount_breakdown[0]['code'])->toBe('AHORRA20')
        ->and($order->discount_breakdown[0]['amount_cents'])->toBe(10000);
});

it('conserva la fotografia aunque la promocion cambie despues', function () {
    $promotion = Promotion::factory()->coupon('AHORRA20')->percentage(20)->create(['name' => 'Nombre original']);

    $product = Product::factory()->withStock(10)->create(['price_cents' => 50000]);

    enviarCheckout([[$product, 1]], ['coupon_code' => 'AHORRA20']);

    // Se cambia la promocion despues de la venta.
    $promotion->forceFill(['name' => 'Nombre nuevo', 'discount_value' => 90])->save();

    $order = Order::firstOrFail();

    // El pedido conserva lo que de verdad se aplico.
    expect($order->discount_cents)->toBe(10000)
        ->and($order->discount_breakdown[0]['name'])->toBe('Nombre original');
});

it('un cupon de envio gratis libera el envio sin alcanzar el umbral', function () {
    Promotion::factory()->coupon('ENVIOGRATIS')->freeShipping()->create();

    $product = Product::factory()->withStock(10)->create(['price_cents' => 20000]);

    enviarCheckout([[$product, 1]], ['coupon_code' => 'ENVIOGRATIS']);

    $order = Order::firstOrFail();

    expect($order->shipping_cents)->toBe(0)
        ->and($order->total_cents)->toBe(20000);
});

it('el descuento puede dejar el pedido por debajo del umbral de envio gratis', function () {
    app(ShippingSettingsRepository::class)->save(new ShippingSettings(
        flatCents: 9900,
        freeShippingThresholdCents: 80000,
        thresholdAfterDiscounts: true,
    ));

    Promotion::factory()->coupon('BAJA')->fixedAmount(20000)->create();

    $product = Product::factory()->withStock(10)->create(['price_cents' => 85000]);

    enviarCheckout([[$product, 1]], ['coupon_code' => 'BAJA']);

    $order = Order::firstOrFail();

    // 850 menos 200 son 650: ya no alcanza el umbral, asi que se cobra envio.
    expect($order->discount_cents)->toBe(20000)
        ->and($order->shipping_cents)->toBe(9900)
        ->and($order->total_cents)->toBe(74900);
});

it('registra el uso del cupon y aumenta su contador', function () {
    $promotion = Promotion::factory()->coupon('UNICO')->percentage(10)->create(['max_uses' => 1]);

    $product = Product::factory()->withStock(10)->create(['price_cents' => 50000]);

    enviarCheckout([[$product, 1]], ['coupon_code' => 'UNICO']);

    $order = Order::firstOrFail();
    $usage = CouponUsage::firstOrFail();

    expect($promotion->fresh()->uses_count)->toBe(1)
        ->and($usage->order_id)->toBe($order->id)
        ->and($usage->email)->toBe('cliente@example.test')
        ->and($usage->discount_cents)->toBe(5000);
});

it('un cupon agotado deja de aplicar en la siguiente compra', function () {
    Promotion::factory()->coupon('UNICO')->percentage(10)->create(['max_uses' => 1]);

    $product = Product::factory()->withStock(10)->create(['price_cents' => 50000]);

    enviarCheckout([[$product, 1]], ['coupon_code' => 'UNICO']);
    enviarCheckout([[$product, 1]], ['coupon_code' => 'UNICO']);

    $pedidos = Order::orderBy('id')->get();

    expect($pedidos)->toHaveCount(2)
        ->and($pedidos[0]->discount_cents)->toBe(5000)
        // El segundo pedido se guarda, pero ya sin descuento.
        ->and($pedidos[1]->discount_cents)->toBe(0);
});

it('el pedido se guarda aunque el cupon no aplique', function () {
    $product = Product::factory()->withStock(10)->create(['price_cents' => 50000]);

    // Un codigo inventado no debe costarle la compra al cliente.
    enviarCheckout([[$product, 1]], ['coupon_code' => 'NOEXISTE'])->assertSessionHasNoErrors();

    $order = Order::firstOrFail();

    expect($order->discount_cents)->toBe(0)
        ->and($order->total_cents)->toBe(59900);
});

it('aplica un 3x2 en el checkout regalando la pieza mas barata', function () {
    Promotion::factory()->buyXGetY(3, 1)->create(['name' => 'Tres por dos']);

    $caro = Product::factory()->withStock(10)->create(['price_cents' => 60000]);
    $barato = Product::factory()->withStock(10)->create(['price_cents' => 20000]);

    enviarCheckout([[$caro, 2], [$barato, 1]])->assertSessionHasNoErrors();

    $order = Order::firstOrFail();

    // Tres piezas: se regala la de $200.
    expect($order->subtotal_cents)->toBe(140000)
        ->and($order->discount_cents)->toBe(20000);
});

it('no cuenta el uso si el pedido se deshace por falta de existencias', function () {
    $promotion = Promotion::factory()->coupon('UNICO')->percentage(10)->create(['max_uses' => 1]);

    $product = Product::factory()->withStock(1)->create(['price_cents' => 50000]);

    enviarCheckout([[$product, 5]], ['coupon_code' => 'UNICO'])->assertSessionHasErrors('cart_payload');

    // Sin pedido no hay consumo: el cupon sigue disponible.
    expect(Order::count())->toBe(0)
        ->and(CouponUsage::count())->toBe(0)
        ->and($promotion->fresh()->uses_count)->toBe(0);
});
