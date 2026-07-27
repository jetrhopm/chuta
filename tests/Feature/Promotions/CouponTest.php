<?php

use App\Models\CouponUsage;
use App\Models\Order;
use App\Models\Product;
use App\Models\Promotion;

it('no aplica un cupon si no se captura su codigo', function () {
    Promotion::factory()->coupon('BIENVENIDA')->percentage(20)->create();

    $product = Product::factory()->create(['price_cents' => 50000]);

    // Un cupon no es una promocion automatica: sin el codigo no hace nada.
    expect(calcular([[$product, 1]])->subtotalDiscountCents())->toBe(0);
});

it('aplica el cupon cuando se captura su codigo', function () {
    Promotion::factory()->coupon('BIENVENIDA')->percentage(20)->create();

    $product = Product::factory()->create(['price_cents' => 50000]);

    expect(calcular([[$product, 1]], ['coupon' => 'BIENVENIDA'])->subtotalDiscountCents())->toBe(10000);
});

it('acepta el codigo escrito de cualquier forma', function () {
    Promotion::factory()->coupon('BIENVENIDA')->percentage(20)->create();

    $product = Product::factory()->create(['price_cents' => 50000]);

    // Los cupones se comparten de boca en boca y nadie respeta mayusculas.
    foreach (['bienvenida', '  Bienvenida  ', 'BiEnVeNiDa'] as $escrito) {
        expect(calcular([[$product, 1]], ['coupon' => $escrito])->subtotalDiscountCents())->toBe(10000);
    }
});

it('explica que el codigo no existe', function () {
    $product = Product::factory()->create(['price_cents' => 50000]);

    $result = calcular([[$product, 1]], ['coupon' => 'INVENTADO']);

    expect($result->subtotalDiscountCents())->toBe(0)
        ->and($result->firstRejection())->toBe('Ese codigo no existe. Revisalo e intenta de nuevo.');
});

it('explica que el cupon ya vencio', function () {
    Promotion::factory()->coupon('VIEJO')->percentage(20)->expired()->create();

    $product = Product::factory()->create(['price_cents' => 50000]);

    expect(calcular([[$product, 1]], ['coupon' => 'VIEJO'])->firstRejection())->toBe('Ese cupon ya vencio.');
});

it('explica que el cupon todavia no empieza', function () {
    Promotion::factory()->coupon('PRONTO')->percentage(20)->future()->create();

    $product = Product::factory()->create(['price_cents' => 50000]);

    expect(calcular([[$product, 1]], ['coupon' => 'PRONTO'])->firstRejection())->toBe('Ese cupon todavia no empieza.');
});

it('dice cuanto falta para alcanzar el minimo', function () {
    Promotion::factory()->coupon('MIL')->percentage(20)->create(['min_subtotal_cents' => 100000]);

    $product = Product::factory()->create(['price_cents' => 60000]);

    // Un "no aplica" a secas no le sirve al cliente; saber que le faltan $400 si.
    expect(calcular([[$product, 1]], ['coupon' => 'MIL'])->firstRejection())
        ->toBe('Te faltan $400.00 para poder usar ese cupon.');
});

it('explica que el cupon no alcanza a los productos del carrito', function () {
    $otro = Product::factory()->create(['price_cents' => 50000]);
    $product = Product::factory()->create(['price_cents' => 50000]);

    Promotion::factory()->coupon('SOLOESE')->percentage(20)->create(['product_ids' => [$otro->id]]);

    expect(calcular([[$product, 1]], ['coupon' => 'SOLOESE'])->firstRejection())
        ->toBe('Ese cupon no aplica a los productos de tu carrito.');
});

it('respeta el limite global de usos', function () {
    $promotion = Promotion::factory()->coupon('LIMITADO')->percentage(20)->create([
        'max_uses' => 2,
    ]);

    $promotion->forceFill(['uses_count' => 2])->save();

    $product = Product::factory()->create(['price_cents' => 50000]);

    expect(calcular([[$product, 1]], ['coupon' => 'LIMITADO'])->firstRejection())
        ->toBe('Ese cupon alcanzo su limite de usos.');
});

it('respeta el limite por cliente contando por correo', function () {
    $promotion = Promotion::factory()->coupon('UNAVEZ')->percentage(20)->create([
        'max_uses_per_customer' => 1,
    ]);

    $order = Order::create([
        'code' => 'CHX-CUPON-0001',
        'payment_method' => 'bank_transfer',
        'subtotal_cents' => 50000,
        'total_cents' => 50000,
        'customer_name' => 'Cliente',
        'customer_phone' => '6441234567',
        'shipping_street' => 'Calle',
        'shipping_neighborhood' => 'Centro',
        'shipping_city' => 'Obregon',
        'shipping_state' => 'Sonora',
        'shipping_postcode' => '85000',
    ]);

    CouponUsage::create([
        'promotion_id' => $promotion->id,
        'order_id' => $order->id,
        'email' => 'Cliente@Example.test',
        'discount_cents' => 10000,
    ]);

    $product = Product::factory()->create(['price_cents' => 50000]);

    // El correo se compara normalizado: quien ya lo uso no puede repetir
    // cambiando mayusculas.
    expect(calcular([[$product, 1]], ['coupon' => 'UNAVEZ', 'email' => 'cliente@example.test'])->firstRejection())
        ->toBe('Ya usaste ese cupon el maximo de veces permitido.');

    // Y otra persona si puede usarlo.
    expect(calcular([[$product, 1]], ['coupon' => 'UNAVEZ', 'email' => 'otro@example.test'])->subtotalDiscountCents())
        ->toBe(10000);
});

it('limita el cupon de primera compra', function () {
    Promotion::factory()->coupon('PRIMERA')->percentage(20)->create(['first_purchase_only' => true]);

    $product = Product::factory()->create(['price_cents' => 50000]);

    expect(calcular([[$product, 1]], ['coupon' => 'PRIMERA', 'first_purchase' => false])->firstRejection())
        ->toBe('Ese cupon es solo para la primera compra.');

    expect(calcular([[$product, 1]], ['coupon' => 'PRIMERA', 'first_purchase' => true])->subtotalDiscountCents())
        ->toBe(10000);
});

it('puede exigir iniciar sesion', function () {
    Promotion::factory()->coupon('SOCIOS')->percentage(20)->create(['allow_guests' => false]);

    $product = Product::factory()->create(['price_cents' => 50000]);

    expect(calcular([[$product, 1]], ['coupon' => 'SOCIOS', 'guest' => true])->firstRejection())
        ->toBe('Necesitas iniciar sesion para usar ese cupon.');

    expect(calcular([[$product, 1]], ['coupon' => 'SOCIOS', 'guest' => false])->subtotalDiscountCents())
        ->toBe(10000);
});

it('puede limitarse a un metodo de pago', function () {
    Promotion::factory()->coupon('SOLOTRANSFER')->percentage(20)->create([
        'payment_methods' => ['bank_transfer'],
    ]);

    $product = Product::factory()->create(['price_cents' => 50000]);

    expect(calcular([[$product, 1]], ['coupon' => 'SOLOTRANSFER', 'payment_method' => 'clip'])->firstRejection())
        ->toBe('Ese cupon no aplica con el metodo de pago elegido.');

    expect(calcular([[$product, 1]], ['coupon' => 'SOLOTRANSFER', 'payment_method' => 'bank_transfer'])->subtotalDiscountCents())
        ->toBe(10000);
});

it('un cupon de envio gratis libera el envio', function () {
    Promotion::factory()->coupon('ENVIOGRATIS')->freeShipping()->create();

    $product = Product::factory()->create(['price_cents' => 20000]);

    $result = calcular([[$product, 1]], ['coupon' => 'ENVIOGRATIS']);

    expect($result->grantsFreeShipping())->toBeTrue()
        ->and($result->subtotalDiscountCents())->toBe(0);
});

it('el cupon se suma a las promociones automaticas', function () {
    Promotion::factory()->percentage(10)->create(['priority' => 10]);
    Promotion::factory()->coupon('EXTRA')->fixedAmount(5000)->create(['priority' => 20]);

    $product = Product::factory()->create(['price_cents' => 100000]);

    expect(calcular([[$product, 1]], ['coupon' => 'EXTRA'])->subtotalDiscountCents())->toBe(15000);
});

it('un cupon exclusivo deja fuera a las promociones automaticas', function () {
    Promotion::factory()->percentage(10)->create(['priority' => 50]);
    Promotion::factory()->coupon('SOLOYO')->fixedAmount(5000)->exclusive()->create(['priority' => 10]);

    $product = Product::factory()->create(['price_cents' => 100000]);

    $result = calcular([[$product, 1]], ['coupon' => 'SOLOYO']);

    expect($result->discounts)->toHaveCount(1)
        ->and($result->subtotalDiscountCents())->toBe(5000);
});

it('no se puede borrar el registro de uso de un cupon', function () {
    $promotion = Promotion::factory()->coupon('AUDITADO')->percentage(10)->create();

    $order = Order::create([
        'code' => 'CHX-CUPON-0002',
        'payment_method' => 'bank_transfer',
        'subtotal_cents' => 50000,
        'total_cents' => 50000,
        'customer_name' => 'Cliente',
        'customer_phone' => '6441234567',
        'shipping_street' => 'Calle',
        'shipping_neighborhood' => 'Centro',
        'shipping_city' => 'Obregon',
        'shipping_state' => 'Sonora',
        'shipping_postcode' => '85000',
    ]);

    $usage = CouponUsage::create([
        'promotion_id' => $promotion->id,
        'order_id' => $order->id,
        'email' => 'cliente@example.test',
        'discount_cents' => 5000,
    ]);

    // Borrarlo permitiria volver a consumir un cupon agotado.
    expect(fn () => $usage->delete())->toThrow(RuntimeException::class);
});
