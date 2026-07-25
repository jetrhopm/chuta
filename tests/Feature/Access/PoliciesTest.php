<?php

use App\Domain\Access\Enums\AdminPermission;
use App\Domain\Access\Enums\AdminRole;
use App\Models\Category;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function crearPedido(string $code): Order
{
    return Order::create([
        'code' => $code,
        'payment_method' => 'bank_transfer',
        'subtotal_cents' => 1000,
        'total_cents' => 1000,
        'customer_name' => 'Cliente',
        'customer_phone' => '6441234567',
        'shipping_street' => 'Calle',
        'shipping_neighborhood' => 'Centro',
        'shipping_city' => 'Obregon',
        'shipping_state' => 'Sonora',
        'shipping_postcode' => '85000',
    ]);
}

it('permite al administrador ver y editar productos', function () {
    $admin = User::factory()->withRole(AdminRole::Admin)->create();
    $product = Product::factory()->create();

    expect($admin->can('viewAny', Product::class))->toBeTrue()
        ->and($admin->can('update', $product))->toBeTrue()
        ->and($admin->can('adjustInventory', $product))->toBeTrue();
});

it('niega borrar productos a quien solo puede archivarlos', function () {
    $admin = User::factory()->withRole(AdminRole::Admin)->create();
    $product = Product::factory()->create();

    // El rol de administrador nace con permiso de archivar pero no de eliminar.
    expect($admin->can('archive', $product))->toBeTrue()
        ->and($admin->can('delete', $product))->toBeFalse();
});

it('niega la gestion de categorias cuando se revoca el permiso al rol', function () {
    $admin = User::factory()->withRole(AdminRole::Admin)->create();

    Role::where('name', AdminRole::Admin->value)
        ->firstOrFail()
        ->revokePermissionTo(AdminPermission::ManageBrandsAndCategories->value);

    $admin = $admin->fresh();

    expect($admin->can('create', Category::class))->toBeFalse()
        ->and($admin->can('update', Category::create(['name' => 'Prueba', 'slug' => 'prueba'])))->toBeFalse();
});

it('niega borrar una categoria con productos a quien no es superadministrador', function () {
    $admin = User::factory()->withRole(AdminRole::Admin)->create();
    $product = Product::factory()->create();

    expect($admin->can('delete', $product->category))->toBeFalse()
        ->and($admin->can('delete', Category::factory()->create()))->toBeTrue();
});

it('impide borrar una categoria con productos incluso saltandose la Policy', function () {
    $product = Product::factory()->create();

    // El superadministrador se salta cualquier Policy por el Gate::before, asi
    // que la invariante tiene que vivir en el modelo para ser una garantia.
    expect(fn () => $product->category->delete())->toThrow(RuntimeException::class);

    expect(Category::factory()->create()->delete())->toBeTrue();
});

it('impide borrar pedidos incluso saltandose la Policy', function () {
    $order = crearPedido('CHX-TEST-00001');

    // Un pedido es un registro contable: se cancela, no se borra.
    expect(fn () => $order->delete())->toThrow(RuntimeException::class)
        ->and(Order::whereKey($order->getKey())->exists())->toBeTrue();
});

it('no deja crear pedidos desde el panel', function () {
    $admin = User::factory()->withRole(AdminRole::Admin)->create();

    // Un pedido creado a mano no pasaria por el recalculo de precios ni por el
    // descuento de inventario que hace el checkout.
    expect($admin->can('create', Order::class))->toBeFalse();
});

it('exige el permiso de cancelaciones para cancelar un pedido', function () {
    $admin = User::factory()->withRole(AdminRole::Admin)->create();
    $order = crearPedido('CHX-TEST-00002');

    // El administrador puede administrar pedidos y su surtido, pero las
    // cancelaciones y reembolsos quedan fuera de sus permisos iniciales.
    expect($admin->can('update', $order))->toBeTrue()
        ->and($admin->can('updateFulfillment', $order))->toBeTrue()
        ->and($admin->can('cancel', $order))->toBeFalse();
});

it('niega al administrador el historial de inventario solo si se le revoca', function () {
    $admin = User::factory()->withRole(AdminRole::Admin)->create();

    expect($admin->can('viewAny', InventoryMovement::class))->toBeTrue();

    Role::where('name', AdminRole::Admin->value)
        ->firstOrFail()
        ->revokePermissionTo(AdminPermission::ViewInventoryMovements->value);

    expect($admin->fresh()->can('viewAny', InventoryMovement::class))->toBeFalse();
});

it('impide escribir en el historial de inventario desde el panel', function () {
    $admin = User::factory()->withRole(AdminRole::Admin)->create();

    expect($admin->can('create', InventoryMovement::class))->toBeFalse()
        ->and($admin->can('deleteAny', InventoryMovement::class))->toBeFalse();
});
