<?php

use App\Domain\Access\Enums\AdminRole;
use App\Domain\Inventory\Actions\RecordInventoryMovement;
use App\Domain\Inventory\Enums\InventoryMovementType;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

/**
 * Carga cada pantalla del panel con datos dentro.
 *
 * Existe porque un error de configuracion de Filament (una columna con un
 * metodo que no existe, por ejemplo) solo aparece al renderizar la tabla, no al
 * registrar la ruta. Sin estas pruebas, una pantalla del panel puede quedar
 * devolviendo 500 sin que nada lo delate.
 */
beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->actingAs(User::factory()->withRole(AdminRole::SuperAdmin)->create());
});

it('renderiza el escritorio', function () {
    $this->get('/admin')->assertOk();
});

it('renderiza el listado de productos con datos', function () {
    Product::factory()->count(3)->create();
    Product::factory()->untracked()->create();
    Product::factory()->withStock(1)->lowStockThreshold(5)->create();

    $this->get('/admin/products')->assertOk();
});

it('renderiza el formulario de producto', function () {
    $product = Product::factory()->create();

    $this->get('/admin/products/create')->assertOk();
    $this->get("/admin/products/{$product->getKey()}/edit")->assertOk();
});

it('renderiza el listado de categorias con datos', function () {
    Product::factory()->count(2)->create();

    $this->get('/admin/categories')->assertOk();
});

it('renderiza el listado de pedidos con datos', function () {
    Order::create([
        'code' => 'CHX-RENDER-0001',
        'payment_method' => 'bank_transfer',
        'subtotal_cents' => 150000,
        'shipping_cents' => 0,
        'total_cents' => 150000,
        'customer_name' => 'Cliente Prueba',
        'customer_email' => 'cliente@example.test',
        'customer_phone' => '6441234567',
        'shipping_street' => 'Calle Uno',
        'shipping_neighborhood' => 'Centro',
        'shipping_city' => 'Obregon',
        'shipping_state' => 'Sonora',
        'shipping_postcode' => '85000',
    ]);

    $this->get('/admin/orders')->assertOk();
});

it('renderiza el historial de inventario con datos', function () {
    $product = Product::factory()->withStock(10)->create();

    app(RecordInventoryMovement::class)->handle(
        product: $product,
        type: InventoryMovementType::Adjustment,
        quantity: -2,
        reason: 'Merma',
        user: auth()->user(),
    );

    $this->get('/admin/inventory-movements')->assertOk();
});
