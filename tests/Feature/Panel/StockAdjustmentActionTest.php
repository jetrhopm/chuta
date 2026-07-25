<?php

use App\Domain\Access\Enums\AdminPermission;
use App\Domain\Access\Enums\AdminRole;
use App\Domain\Inventory\Enums\InventoryMovementType;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('ajusta existencias desde el panel y deja constancia de quien lo hizo', function () {
    $admin = User::factory()->withRole(AdminRole::Admin)->create();
    $this->actingAs($admin);

    $product = Product::factory()->withStock(10)->create();

    Livewire::test(ListProducts::class)
        ->callAction(
            TestAction::make('ajustarExistencias')->table($product),
            data: ['quantity' => -3, 'reason' => 'Merma por caducidad'],
        )
        ->assertHasNoActionErrors();

    expect($product->fresh()->stock)->toBe(7);

    $movement = InventoryMovement::firstOrFail();

    expect($movement->type)->toBe(InventoryMovementType::Adjustment)
        ->and($movement->quantity)->toBe(-3)
        ->and($movement->reason)->toBe('Merma por caducidad')
        // Un ajuste manual si tiene autor, al contrario que una venta.
        ->and($movement->user_id)->toBe($admin->getKey());
});

it('rechaza un ajuste que dejaria las existencias en negativo', function () {
    $this->actingAs(User::factory()->withRole(AdminRole::Admin)->create());

    $product = Product::factory()->withStock(2)->create();

    Livewire::test(ListProducts::class)
        ->callAction(
            TestAction::make('ajustarExistencias')->table($product),
            data: ['quantity' => -5, 'reason' => 'Intento invalido'],
        );

    expect($product->fresh()->stock)->toBe(2)
        ->and(InventoryMovement::count())->toBe(0);
});

it('esconde el ajuste a quien no tiene permiso de inventario', function () {
    $admin = User::factory()->withRole(AdminRole::Admin)->create();

    Role::where('name', AdminRole::Admin->value)
        ->firstOrFail()
        ->revokePermissionTo(AdminPermission::AdjustInventory->value);

    $this->actingAs($admin->fresh());

    $product = Product::factory()->withStock(10)->create();

    Livewire::test(ListProducts::class)
        ->assertActionHidden(TestAction::make('ajustarExistencias')->table($product));
});

it('cancela un pedido desde el panel y repone el inventario', function () {
    $superadmin = User::factory()->withRole(AdminRole::SuperAdmin)->create();
    $this->actingAs($superadmin);

    $product = Product::factory()->withStock(10)->create();

    enviarCheckout([[$product, 4]]);
    expect($product->fresh()->stock)->toBe(6);

    $order = Order::firstOrFail();

    Livewire::test(ListOrders::class)
        ->callAction(
            TestAction::make('cancelar')->table($order),
            data: ['reason' => 'El cliente ya no lo quiere'],
        )
        ->assertHasNoActionErrors();

    expect($product->fresh()->stock)->toBe(10)
        ->and($order->fresh()->status)->toBe('cancelled');

    $reposicion = InventoryMovement::where('type', InventoryMovementType::Cancellation)->firstOrFail();

    expect($reposicion->quantity)->toBe(4)
        ->and($reposicion->user_id)->toBe($superadmin->getKey());
});

it('esconde la cancelacion a quien no tiene permiso de reembolsos', function () {
    // El rol de administrador nace sin el permiso de cancelaciones.
    $this->actingAs(User::factory()->withRole(AdminRole::Admin)->create());

    $product = Product::factory()->withStock(10)->create();
    enviarCheckout([[$product, 1]]);

    Livewire::test(ListOrders::class)
        ->assertActionHidden(TestAction::make('cancelar')->table(Order::firstOrFail()));
});
