<?php

use App\Domain\Inventory\Actions\RecordInventoryMovement;
use App\Domain\Inventory\Enums\InventoryMovementType;
use App\Domain\Inventory\Exceptions\InsufficientStock;
use App\Models\InventoryMovement;
use App\Models\Product;

it('descuenta existencias y deja constancia en el historial', function () {
    $product = Product::factory()->withStock(10)->create();

    app(RecordInventoryMovement::class)->handle(
        product: $product,
        type: InventoryMovementType::Sale,
        quantity: -3,
        reason: 'Venta de prueba',
    );

    expect($product->fresh()->stock)->toBe(7);

    $movement = InventoryMovement::firstOrFail();

    expect($movement->quantity)->toBe(-3)
        ->and($movement->stock_after)->toBe(7)
        ->and($movement->type)->toBe(InventoryMovementType::Sale);
});

it('suma existencias en una entrada', function () {
    $product = Product::factory()->withStock(4)->create();

    app(RecordInventoryMovement::class)->handle(
        product: $product,
        type: InventoryMovementType::Entry,
        quantity: 6,
    );

    expect($product->fresh()->stock)->toBe(10);
});

it('rechaza un movimiento que dejaria las existencias en negativo', function () {
    $product = Product::factory()->withStock(2)->create();

    expect(fn () => app(RecordInventoryMovement::class)->handle(
        product: $product,
        type: InventoryMovementType::Sale,
        quantity: -3,
    ))->toThrow(InsufficientStock::class);

    // Las existencias no se movieron: el fallo no deja el inventario a medias.
    expect($product->fresh()->stock)->toBe(2)
        ->and(InventoryMovement::count())->toBe(0);
});

it('actualiza la instancia que recibio quien llamo', function () {
    $product = Product::factory()->withStock(10)->create();

    app(RecordInventoryMovement::class)->handle(
        product: $product,
        type: InventoryMovementType::Sale,
        quantity: -4,
    );

    // Sin esto, quien llamo seguiria trabajando con unas existencias caducas.
    expect($product->stock)->toBe(6);
});

it('no acepta movimientos de cero piezas', function () {
    $product = Product::factory()->create();

    expect(fn () => app(RecordInventoryMovement::class)->handle(
        product: $product,
        type: InventoryMovementType::Adjustment,
        quantity: 0,
    ))->toThrow(InvalidArgumentException::class);
});

it('impide modificar un movimiento ya registrado', function () {
    $movement = app(RecordInventoryMovement::class)->handle(
        product: Product::factory()->create(),
        type: InventoryMovementType::Entry,
        quantity: 5,
    );

    expect(fn () => $movement->update(['quantity' => 999]))->toThrow(RuntimeException::class);
});

it('impide eliminar un movimiento ya registrado', function () {
    $movement = app(RecordInventoryMovement::class)->handle(
        product: Product::factory()->create(),
        type: InventoryMovementType::Entry,
        quantity: 5,
    );

    expect(fn () => $movement->delete())->toThrow(RuntimeException::class);
});

it('detecta existencias bajas solo cuando hay umbral', function () {
    $conUmbral = Product::factory()->withStock(2)->lowStockThreshold(3)->create();
    $sinUmbral = Product::factory()->withStock(0)->create();
    $holgado = Product::factory()->withStock(20)->lowStockThreshold(3)->create();

    expect($conUmbral->hasLowStock())->toBeTrue()
        ->and($sinUmbral->hasLowStock())->toBeFalse()
        ->and($holgado->hasLowStock())->toBeFalse();

    $bajos = Product::lowStock()->pluck('id');

    expect($bajos)->toContain($conUmbral->id)
        ->and($bajos)->not->toContain($sinUmbral->id)
        ->and($bajos)->not->toContain($holgado->id);
});

it('no limita la venta de un producto que no se lleva por existencias', function () {
    $product = Product::factory()->untracked()->create();

    expect($product->canFulfill(500))->toBeTrue()
        ->and($product->availableQuantity())->toBeNull()
        ->and($product->is_in_stock)->toBeTrue();
});
