<?php

use App\Models\Order;
use App\Models\Product;

it('sirve la pagina de inicio', function () {
    $this->get('/')->assertOk();
});

it('muestra el catalogo inicial en la tienda', function () {
    $this->seed();

    $this->get('/')
        ->assertOk()
        ->assertSee('Chutamax')
        ->assertSee('BSN No-Xplode Pre Entreno 60 servicios')
        ->assertSee('Optimum Nutrition Proteina Gold Standard 100% Whey 5 libras');
});

it('protege el panel administrativo de visitantes anonimos', function () {
    $this->get('/admin')->assertRedirect('/admin/login');
});

it('guarda pedidos del checkout y recalcula el total en servidor', function () {
    $this->seed();

    $product = Product::where('is_active', true)->firstOrFail();

    $this->post('/checkout', [
        'cart_payload' => json_encode([
            ['id' => $product->id, 'quantity' => 2, 'price_cents' => 1],
        ]),
        'customer_name' => 'Cliente Prueba',
        'customer_email' => 'cliente@example.test',
        'customer_phone' => '6441234567',
        'shipping_street' => 'Calle Uno',
        'shipping_number' => '123',
        'shipping_neighborhood' => 'Centro',
        'shipping_city' => 'Obregon',
        'shipping_state' => 'Sonora',
        'shipping_postcode' => '85000',
        'payment_method' => 'bank_transfer',
    ])->assertRedirect('/');

    $order = Order::with('items')->firstOrFail();

    expect($order->total_cents)->toBe($product->price_cents * 2)
        ->and($order->items)->toHaveCount(1)
        ->and($order->items->first()->product_name)->toBe($product->name);
});
