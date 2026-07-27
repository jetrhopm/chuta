<?php

use App\Domain\Access\Enums\AdminPermission;
use App\Domain\Access\Enums\AdminRole;
use App\Domain\Promotions\Enums\DiscountType;
use App\Filament\Resources\Promotions\Pages\CreatePromotion;
use App\Filament\Resources\Promotions\Pages\EditPromotion;
use App\Filament\Resources\Promotions\Pages\ListPromotions;
use App\Models\CouponUsage;
use App\Models\Order;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->actingAs(User::factory()->withRole(AdminRole::Admin)->create());
});

it('renderiza el listado con datos', function () {
    Promotion::factory()->percentage(10)->create();
    Promotion::factory()->coupon('CUPON')->fixedAmount(5000)->create();
    Promotion::factory()->buyXGetY(3, 1)->create();
    Promotion::factory()->freeShipping()->create();

    $this->get('/admin/promotions')->assertOk();
});

it('crea un cupon porcentual desde el panel', function () {
    Livewire::test(CreatePromotion::class)
        ->fillForm([
            'name' => 'Bienvenida',
            'description' => 'Veinte por ciento en tu primera compra',
            'requires_code' => true,
            'code' => 'bienvenida',
            'discount_type' => DiscountType::Percentage->value,
            'discount_value' => 20,
            'first_purchase_only' => true,
            'max_uses_per_customer' => 1,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $promotion = Promotion::firstOrFail();

    expect($promotion->requires_code)->toBeTrue()
        // El codigo se normaliza al guardar.
        ->and($promotion->code)->toBe('BIENVENIDA')
        ->and($promotion->discount_type)->toBe(DiscountType::Percentage)
        ->and($promotion->first_purchase_only)->toBeTrue()
        ->and($promotion->max_uses_per_customer)->toBe(1);
});

it('crea un 3x2 desde el panel', function () {
    Livewire::test(CreatePromotion::class)
        ->fillForm([
            'name' => 'Tres por dos',
            'requires_code' => false,
            'discount_type' => DiscountType::BuyXGetY->value,
            'buy_quantity' => 3,
            'get_quantity' => 1,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $promotion = Promotion::firstOrFail();

    expect($promotion->discount_type)->toBe(DiscountType::BuyXGetY)
        ->and($promotion->buy_quantity)->toBe(3)
        ->and($promotion->get_quantity)->toBe(1)
        ->and($promotion->requires_code)->toBeFalse();
});

it('guarda el alcance por categoria y las exclusiones', function () {
    $product = Product::factory()->create();

    Livewire::test(CreatePromotion::class)
        ->fillForm([
            'name' => 'Solo proteinas',
            'discount_type' => DiscountType::Percentage->value,
            'discount_value' => 15,
            'category_ids' => [$product->category_id],
            'excluded_product_ids' => [$product->id],
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $promotion = Promotion::firstOrFail();

    // El selector guarda los identificadores como los entrega el formulario, asi
    // que se comparan sin exigir el tipo: lo que importa es que la promocion
    // reconozca su alcance, y coversProduct compara de forma laxa.
    expect($promotion->category_ids)->toHaveCount(1)
        ->and((int) $promotion->category_ids[0])->toBe($product->category_id)
        ->and((int) $promotion->excluded_product_ids[0])->toBe($product->id)
        // La exclusion gana sobre la inclusion por categoria.
        ->and($promotion->coversProduct($product))->toBeFalse();
});

it('exige un codigo cuando la promocion lo necesita', function () {
    Livewire::test(CreatePromotion::class)
        ->fillForm([
            'name' => 'Sin codigo',
            'requires_code' => true,
            'code' => '',
            'discount_type' => DiscountType::Percentage->value,
            'discount_value' => 10,
        ])
        ->call('create')
        ->assertHasFormErrors(['code']);
});

it('no permite dos cupones con el mismo codigo', function () {
    Promotion::factory()->coupon('REPETIDO')->percentage(10)->create();

    Livewire::test(CreatePromotion::class)
        ->fillForm([
            'name' => 'Otro',
            'requires_code' => true,
            'code' => 'REPETIDO',
            'discount_type' => DiscountType::Percentage->value,
            'discount_value' => 10,
        ])
        ->call('create')
        ->assertHasFormErrors(['code']);
});

it('rechaza una vigencia que termina antes de empezar', function () {
    Livewire::test(CreatePromotion::class)
        ->fillForm([
            'name' => 'Fechas al reves',
            'discount_type' => DiscountType::Percentage->value,
            'discount_value' => 10,
            'starts_at' => now()->addWeek(),
            'ends_at' => now()->addDay(),
        ])
        ->call('create')
        ->assertHasFormErrors(['ends_at']);
});

it('deja editar una promocion existente', function () {
    $promotion = Promotion::factory()->percentage(10)->create(['name' => 'Nombre viejo']);

    Livewire::test(EditPromotion::class, ['record' => $promotion->getKey()])
        ->assertFormSet(['name' => 'Nombre viejo', 'discount_value' => 10])
        ->fillForm(['name' => 'Nombre nuevo', 'discount_value' => 25])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($promotion->fresh()->name)->toBe('Nombre nuevo')
        ->and($promotion->fresh()->discount_value)->toBe(25);
});

it('impide borrar una promocion que ya se uso', function () {
    $promotion = Promotion::factory()->coupon('USADO')->percentage(10)->create();

    $order = Order::create([
        'code' => 'CHX-PROMO-0001',
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
        'email' => 'cliente@example.test',
        'discount_cents' => 5000,
    ]);

    // Borrarla eliminaria el registro de usos que sostiene sus limites.
    // Desactivarla es lo correcto.
    expect(auth()->user()->can('delete', $promotion))->toBeFalse();

    Livewire::test(ListPromotions::class)
        ->assertActionHidden(TestAction::make('delete')->table($promotion));
});

it('permite borrar una promocion que nunca se uso', function () {
    $promotion = Promotion::factory()->percentage(10)->create();

    expect(auth()->user()->can('delete', $promotion))->toBeTrue();
});

it('niega la pantalla a quien no puede gestionar promociones', function () {
    Role::where('name', AdminRole::Admin->value)
        ->firstOrFail()
        ->revokePermissionTo(AdminPermission::ManagePromotions->value);

    $this->actingAs(auth()->user()->fresh());

    $this->get('/admin/promotions')->assertForbidden();
});
