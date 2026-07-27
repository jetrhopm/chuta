<?php

use App\Domain\Promotions\Actions\CalculateDiscounts;
use App\Domain\Promotions\Data\CartLine;
use App\Domain\Promotions\Data\DiscountContext;
use App\Models\Category;
use App\Models\Product;
use App\Models\Promotion;

/**
 * @param  array<int, array{0: Product, 1: int}>  $lineas
 */
function contexto(array $lineas, array $extra = []): DiscountContext
{
    return new DiscountContext(
        lines: array_map(
            fn (array $l): CartLine => CartLine::forProduct($l[0], $l[1]),
            $lineas,
        ),
        couponCode: $extra['coupon'] ?? null,
        email: $extra['email'] ?? null,
        paymentMethod: $extra['payment_method'] ?? null,
        isFirstPurchase: $extra['first_purchase'] ?? false,
        isGuest: $extra['guest'] ?? true,
    );
}

function calcular(array $lineas, array $extra = [])
{
    return app(CalculateDiscounts::class)->handle(contexto($lineas, $extra));
}

it('no descuenta nada si no hay promociones', function () {
    $product = Product::factory()->create(['price_cents' => 50000]);

    expect(calcular([[$product, 2]])->subtotalDiscountCents())->toBe(0);
});

it('aplica un descuento porcentual sobre el subtotal', function () {
    Promotion::factory()->percentage(10)->create();

    $product = Product::factory()->create(['price_cents' => 50000]);

    // 10% de $1,000 = $100
    expect(calcular([[$product, 2]])->subtotalDiscountCents())->toBe(10000);
});

it('redondea el porcentaje hacia abajo al centavo', function () {
    Promotion::factory()->percentage(10)->create();

    // 10% de 1999 centavos son 199.9: se queda en 199 para no regalar una
    // fraccion que no existe.
    $product = Product::factory()->create(['price_cents' => 1999]);

    expect(calcular([[$product, 1]])->subtotalDiscountCents())->toBe(199);
});

it('aplica un descuento de monto fijo', function () {
    Promotion::factory()->fixedAmount(15000)->create();

    $product = Product::factory()->create(['price_cents' => 50000]);

    expect(calcular([[$product, 1]])->subtotalDiscountCents())->toBe(15000);
});

it('no deja que un monto fijo supere el subtotal', function () {
    Promotion::factory()->fixedAmount(100000)->create();

    $product = Product::factory()->create(['price_cents' => 30000]);

    // Sin este tope el total quedaria en negativo.
    expect(calcular([[$product, 1]])->subtotalDiscountCents())->toBe(30000);
});

it('respeta el beneficio maximo', function () {
    Promotion::factory()->percentage(50)->create(['max_benefit_cents' => 20000]);

    $product = Product::factory()->create(['price_cents' => 100000]);

    // 50% serian $500, pero el tope son $200.
    expect(calcular([[$product, 1]])->subtotalDiscountCents())->toBe(20000);
});

it('regala la pieza mas barata en un 3x2', function () {
    Promotion::factory()->buyXGetY(3, 1)->create();

    $caro = Product::factory()->create(['price_cents' => 60000]);
    $medio = Product::factory()->create(['price_cents' => 50000]);
    $barato = Product::factory()->create(['price_cents' => 30000]);

    // Tres piezas, un grupo completo: se regala la de $300.
    expect(calcular([[$caro, 1], [$medio, 1], [$barato, 1]])->subtotalDiscountCents())->toBe(30000);
});

it('regala una pieza por cada grupo completo de tres', function () {
    Promotion::factory()->buyXGetY(3, 1)->create();

    $product = Product::factory()->create(['price_cents' => 10000]);

    // Seis piezas son dos grupos: dos gratis.
    expect(calcular([[$product, 6]])->subtotalDiscountCents())->toBe(20000);

    // Siete piezas siguen siendo dos grupos: la septima no completa el tercero.
    expect(calcular([[$product, 7]])->subtotalDiscountCents())->toBe(20000);
});

it('no regala nada si no se completa el grupo', function () {
    Promotion::factory()->buyXGetY(3, 1)->create();

    $product = Product::factory()->create(['price_cents' => 10000]);

    expect(calcular([[$product, 2]])->subtotalDiscountCents())->toBe(0);
});

it('aplica un 2x1 regalando la mitad de cada par', function () {
    Promotion::factory()->buyXGetY(2, 1)->create();

    $product = Product::factory()->create(['price_cents' => 20000]);

    // Cuatro piezas, dos pares: dos gratis.
    expect(calcular([[$product, 4]])->subtotalDiscountCents())->toBe(40000);
});

it('marca el envio gratis sin rebajar el subtotal', function () {
    Promotion::factory()->freeShipping()->create();

    $product = Product::factory()->create(['price_cents' => 50000]);

    $result = calcular([[$product, 1]]);

    // El envio lo resuelve el calculo de envio; aqui solo se marca.
    expect($result->grantsFreeShipping())->toBeTrue()
        ->and($result->subtotalDiscountCents())->toBe(0);
});

it('exige el subtotal minimo', function () {
    Promotion::factory()->percentage(10)->create(['min_subtotal_cents' => 80000]);

    $product = Product::factory()->create(['price_cents' => 50000]);

    expect(calcular([[$product, 1]])->subtotalDiscountCents())->toBe(0)
        ->and(calcular([[$product, 2]])->subtotalDiscountCents())->toBe(10000);
});

it('exige la cantidad minima', function () {
    Promotion::factory()->percentage(10)->create(['min_quantity' => 3]);

    $product = Product::factory()->create(['price_cents' => 10000]);

    expect(calcular([[$product, 2]])->subtotalDiscountCents())->toBe(0)
        ->and(calcular([[$product, 3]])->subtotalDiscountCents())->toBe(3000);
});

it('ignora promociones inactivas, vencidas y futuras', function () {
    Promotion::factory()->percentage(50)->inactive()->create();
    Promotion::factory()->percentage(50)->expired()->create();
    Promotion::factory()->percentage(50)->future()->create();

    $product = Product::factory()->create(['price_cents' => 50000]);

    expect(calcular([[$product, 1]])->subtotalDiscountCents())->toBe(0);
});

it('limita la promocion a los productos de su alcance', function () {
    $incluido = Product::factory()->create(['price_cents' => 50000]);
    $fuera = Product::factory()->create(['price_cents' => 50000]);

    Promotion::factory()->percentage(10)->create(['product_ids' => [$incluido->id]]);

    // El 10% se calcula solo sobre el producto incluido, no sobre todo el carrito.
    expect(calcular([[$incluido, 1], [$fuera, 1]])->subtotalDiscountCents())->toBe(5000);
});

it('limita la promocion a una categoria', function () {
    $categoria = Category::factory()->create();
    $dentro = Product::factory()->create(['category_id' => $categoria->id, 'price_cents' => 40000]);
    $fuera = Product::factory()->create(['price_cents' => 40000]);

    Promotion::factory()->percentage(25)->create(['category_ids' => [$categoria->id]]);

    expect(calcular([[$dentro, 1], [$fuera, 1]])->subtotalDiscountCents())->toBe(10000);
});

it('las exclusiones ganan sobre las inclusiones', function () {
    $categoria = Category::factory()->create();
    $excluido = Product::factory()->create(['category_id' => $categoria->id, 'price_cents' => 40000]);
    $incluido = Product::factory()->create(['category_id' => $categoria->id, 'price_cents' => 40000]);

    Promotion::factory()->percentage(50)->create([
        'category_ids' => [$categoria->id],
        'excluded_product_ids' => [$excluido->id],
    ]);

    expect(calcular([[$excluido, 1], [$incluido, 1]])->subtotalDiscountCents())->toBe(20000);
});

it('acumula promociones combinables', function () {
    Promotion::factory()->percentage(10)->create(['priority' => 10]);
    Promotion::factory()->fixedAmount(5000)->create(['priority' => 20]);

    $product = Product::factory()->create(['price_cents' => 100000]);

    // $100 del porcentaje mas $50 del monto fijo.
    expect(calcular([[$product, 1]])->subtotalDiscountCents())->toBe(15000);
});

it('una promocion exclusiva se aplica sola', function () {
    Promotion::factory()->percentage(10)->create(['priority' => 10]);
    Promotion::factory()->fixedAmount(5000)->exclusive()->create(['priority' => 5]);

    $product = Product::factory()->create(['price_cents' => 100000]);

    $result = calcular([[$product, 1]]);

    // Entra la exclusiva por tener prioridad menor y deja fuera a la otra.
    expect($result->discounts)->toHaveCount(1)
        ->and($result->subtotalDiscountCents())->toBe(5000);
});

it('respeta el orden de prioridad', function () {
    Promotion::factory()->percentage(10)->create(['priority' => 50, 'name' => 'Segunda']);
    Promotion::factory()->percentage(20)->create(['priority' => 10, 'name' => 'Primera']);

    $product = Product::factory()->create(['price_cents' => 100000]);

    $result = calcular([[$product, 1]]);

    expect($result->discounts[0]->name)->toBe('Primera')
        ->and($result->discounts[1]->name)->toBe('Segunda');
});

it('nunca descuenta mas que el subtotal', function () {
    Promotion::factory()->fixedAmount(80000)->create(['priority' => 10]);
    Promotion::factory()->fixedAmount(80000)->create(['priority' => 20]);

    $product = Product::factory()->create(['price_cents' => 100000]);

    // Dos descuentos de $800 sobre $1,000 no pueden dejar el total en negativo.
    expect(calcular([[$product, 1]])->subtotalDiscountCents())->toBe(100000);
});

it('deja una fotografia del descuento para guardar con el pedido', function () {
    Promotion::factory()->percentage(10)->create(['name' => 'Diez por ciento', 'description' => 'Promocion de prueba']);

    $product = Product::factory()->create(['price_cents' => 50000]);

    $breakdown = calcular([[$product, 1]])->toBreakdown();

    expect($breakdown)->toHaveCount(1)
        ->and($breakdown[0]['name'])->toBe('Diez por ciento')
        ->and($breakdown[0]['amount_cents'])->toBe(5000)
        ->and($breakdown[0]['type'])->toBe('percentage');
});
