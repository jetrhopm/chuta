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

it('sirve paginas legales y error 404 personalizado', function () {
    $this->get('/politica-de-envios')
        ->assertOk()
        ->assertSee('Politica de envios');

    $this->get('/ruta-que-no-existe')
        ->assertNotFound()
        ->assertSee('No encontramos esa pagina');
});

it('guarda pedidos del checkout y recalcula el total en servidor', function () {
    $this->seed();

    $product = Product::where('is_active', true)->firstOrFail();

    // El precio del formulario es deliberadamente falso: el servidor lo ignora y
    // recalcula con el del catalogo.
    enviarCheckout([[$product, 2]])->assertSessionHasNoErrors();

    $order = Order::with('items')->firstOrFail();

    $subtotal = $product->price_cents * 2;
    $shipping = $subtotal >= config('store.free_shipping_threshold_cents') ? 0 : config('store.shipping_flat_cents');

    expect($order->subtotal_cents)->toBe($subtotal)
        ->and($order->shipping_cents)->toBe($shipping)
        ->and($order->total_cents)->toBe($subtotal + $shipping)
        ->and($order->items)->toHaveCount(1)
        ->and($order->items->first()->product_name)->toBe($product->name);
});
