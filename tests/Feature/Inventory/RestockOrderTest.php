<?php

use App\Domain\Inventory\Actions\RecordInventoryMovement;
use App\Domain\Inventory\Actions\RestockOrder;
use App\Domain\Inventory\Enums\InventoryMovementType;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

it('aplica el bloqueo de fila al mover inventario', function () {
    $product = Product::factory()->withStock(5)->create();

    $consultas = [];
    DB::listen(function ($query) use (&$consultas) {
        $consultas[] = strtolower($query->sql);
    });

    app(RecordInventoryMovement::class)->handle(
        product: $product,
        type: InventoryMovementType::Sale,
        quantity: -1,
    );

    // Es lo que impide que dos compras simultaneas vendan la misma ultima
    // pieza. Si alguien quita el lockForUpdate, esta prueba lo detecta.
    $bloqueo = collect($consultas)->contains(
        fn (string $sql): bool => str_contains($sql, 'select') && str_contains($sql, 'for update'),
    );

    expect($bloqueo)->toBeTrue();
});

it('repone el inventario al cancelar un pedido', function () {
    $product = Product::factory()->withStock(10)->create();

    enviarCheckout([[$product, 4]])->assertSessionHasNoErrors();
    expect($product->fresh()->stock)->toBe(6);

    $order = Order::with('items.product')->firstOrFail();

    app(RestockOrder::class)->handle($order);

    expect($product->fresh()->stock)->toBe(10);

    $reposicion = InventoryMovement::where('type', InventoryMovementType::Cancellation)->firstOrFail();

    expect($reposicion->quantity)->toBe(4)
        ->and($reposicion->reference)->toBe($order->code);
});

it('no repone dos veces el mismo pedido', function () {
    $product = Product::factory()->withStock(10)->create();

    enviarCheckout([[$product, 4]])->assertSessionHasNoErrors();

    $order = Order::with('items.product')->firstOrFail();

    app(RestockOrder::class)->handle($order);
    app(RestockOrder::class)->handle($order);
    app(RestockOrder::class)->handle($order);

    // Cancelar de nuevo no puede inventar piezas que no existen.
    expect($product->fresh()->stock)->toBe(10)
        ->and(InventoryMovement::where('type', InventoryMovementType::Cancellation)->count())->toBe(1);
});

it('registra una devolucion como tal y no como cancelacion', function () {
    $product = Product::factory()->withStock(10)->create();

    enviarCheckout([[$product, 2]])->assertSessionHasNoErrors();

    $order = Order::with('items.product')->firstOrFail();

    app(RestockOrder::class)->handle(
        order: $order,
        type: InventoryMovementType::CustomerReturn,
        reason: 'El cliente devolvio el producto',
    );

    expect($product->fresh()->stock)->toBe(10)
        ->and(InventoryMovement::where('type', InventoryMovementType::CustomerReturn)->count())->toBe(1)
        ->and(InventoryMovement::where('type', InventoryMovementType::Cancellation)->count())->toBe(0);
});

it('no repone un pedido que nunca descontó existencias', function () {
    $product = Product::factory()->untracked()->create();

    enviarCheckout([[$product, 3]])->assertSessionHasNoErrors();

    $order = Order::with('items.product')->firstOrFail();

    app(RestockOrder::class)->handle($order);

    expect(InventoryMovement::count())->toBe(0);
});
