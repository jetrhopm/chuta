<?php

use App\Domain\Access\Enums\AdminPermission;
use App\Domain\Access\Enums\AdminRole;
use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Models\Customer;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('crea historial de cliente y libreta de direcciones al comprar como invitado', function () {
    $product = Product::factory()->withStock(5)->create(['price_cents' => 50000]);

    enviarCheckout([[$product, 2]], [
        'customer_name' => 'Cliente Uno',
        'customer_email' => 'CLIENTE@EXAMPLE.TEST',
        'customer_phone' => '6441234567',
        'shipping_street' => 'Calle Uno',
        'shipping_number' => '123',
        'shipping_neighborhood' => 'Centro',
    ])->assertRedirect();

    $customer = Customer::firstOrFail();

    expect($customer->normalized_email)->toBe('cliente@example.test')
        ->and($customer->orders_count)->toBe(1)
        ->and($customer->lifetime_value_cents)->toBeGreaterThanOrEqual(100000)
        ->and($customer->orders)->toHaveCount(1)
        ->and($customer->addresses)->toHaveCount(1)
        ->and($customer->addresses->first()->street)->toBe('Calle Uno');
});

it('reutiliza el cliente cuando vuelve a comprar con el mismo correo', function () {
    $product = Product::factory()->withStock(5)->create(['price_cents' => 25000]);

    enviarCheckout([[$product, 1]], ['customer_email' => 'cliente@example.test'])->assertRedirect();
    enviarCheckout([[$product, 1]], ['customer_email' => 'CLIENTE@example.test'])->assertRedirect();

    expect(Customer::count())->toBe(1)
        ->and(Customer::first()->orders_count)->toBe(2);
});

it('renderiza el listado de clientes en el panel', function () {
    $this->actingAs(User::factory()->withRole(AdminRole::Admin)->create());

    Customer::create([
        'name' => 'Cliente panel',
        'email' => 'cliente@example.test',
        'normalized_email' => 'cliente@example.test',
        'phone' => '6441234567',
    ]);

    Livewire::test(ListCustomers::class)
        ->assertOk()
        ->assertSee('Cliente panel');
});

it('niega clientes a quien no tiene permiso', function () {
    $admin = User::factory()->withRole(AdminRole::Admin)->create();

    Role::where('name', AdminRole::Admin->value)
        ->firstOrFail()
        ->revokePermissionTo(AdminPermission::ViewCustomers->value);

    $this->actingAs($admin->fresh());

    expect($admin->fresh()->can('viewAny', Customer::class))->toBeFalse();
});
