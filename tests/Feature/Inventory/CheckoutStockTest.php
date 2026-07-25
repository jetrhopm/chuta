<?php

use App\Domain\Inventory\Enums\InventoryMovementType;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Testing\TestResponse;

/**
 * @param  array<int, array{0: Product, 1: int}>  $lineas
 */
function enviarCheckout(array $lineas): TestResponse
{
    $payload = array_map(
        fn (array $linea): array => ['id' => $linea[0]->id, 'quantity' => $linea[1]],
        $lineas,
    );

    return test()->post('/checkout', [
        'cart_payload' => json_encode($payload),
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
    ]);
}

it('descuenta el inventario al confirmar un pedido', function () {
    $product = Product::factory()->withStock(10)->create();

    enviarCheckout([[$product, 3]])->assertRedirect('/');

    expect($product->fresh()->stock)->toBe(7);

    $order = Order::firstOrFail();
    $movement = InventoryMovement::where('order_id', $order->id)->firstOrFail();

    expect($movement->quantity)->toBe(-3)
        ->and($movement->type)->toBe(InventoryMovementType::Sale)
        ->and($movement->reference)->toBe($order->code)
        // El movimiento de una venta no tiene autor: no lo hizo nadie del panel.
        ->and($movement->user_id)->toBeNull();
});

it('rechaza el pedido cuando se piden mas piezas de las que hay', function () {
    $product = Product::factory()->withStock(2)->create();

    enviarCheckout([[$product, 5]])->assertSessionHasErrors('cart_payload');

    // Ni pedido ni movimiento: el intento no deja rastro en el inventario.
    expect(Order::count())->toBe(0)
        ->and(InventoryMovement::count())->toBe(0)
        ->and($product->fresh()->stock)->toBe(2);
});

it('explica al cliente cuantas piezas quedan sin tecnicismos', function () {
    $product = Product::factory()->withStock(2)->create(['name' => 'Proteina de prueba']);

    enviarCheckout([[$product, 5]]);

    $errores = session('errors')->get('cart_payload');

    expect($errores[0])->toContain('Solo quedan 2')
        ->and($errores[0])->toContain('Proteina de prueba')
        ->and($errores[0])->not->toContain('SQL')
        ->and($errores[0])->not->toContain('Exception');
});

it('permite agotar justo las ultimas piezas', function () {
    $product = Product::factory()->withStock(4)->create();

    enviarCheckout([[$product, 4]])->assertRedirect('/');

    expect($product->fresh()->stock)->toBe(0)
        ->and(Order::count())->toBe(1);
});

it('no guarda el pedido si un renglon posterior se queda sin existencias', function () {
    $disponible = Product::factory()->withStock(10)->create();
    $agotado = Product::factory()->withStock(1)->create();

    enviarCheckout([[$disponible, 2], [$agotado, 5]])
        ->assertSessionHasErrors('cart_payload');

    // Lo importante: el primer renglon tampoco se descuenta. La transaccion
    // deshace el pedido completo en lugar de dejarlo a medias.
    expect(Order::count())->toBe(0)
        ->and(InventoryMovement::count())->toBe(0)
        ->and($disponible->fresh()->stock)->toBe(10)
        ->and($agotado->fresh()->stock)->toBe(1);
});

it('no descuenta nada de un producto que no se lleva por existencias', function () {
    $product = Product::factory()->untracked()->create();

    enviarCheckout([[$product, 25]])->assertRedirect('/');

    expect(Order::count())->toBe(1)
        ->and($product->fresh()->stock)->toBe(0)
        ->and(InventoryMovement::count())->toBe(0);
});
