<?php

use App\Models\Category;
use App\Models\Product;

it('muestra la pagina de un producto', function () {
    $product = Product::factory()->withStock(10)->create([
        'name' => 'Proteina Gold Standard',
        'sku' => 'CHX-1001',
        'price_cents' => 129900,
        'short_description' => 'Cinco libras, sabor chocolate.',
    ]);

    $this->get(route('products.show', ['slug' => $product->slug]))
        ->assertOk()
        ->assertSee('Proteina Gold Standard')
        ->assertSee('CHX-1001')
        ->assertSee('$1,299.00')
        ->assertSee('Cinco libras, sabor chocolate.')
        ->assertSee('Disponible')
        ->assertSee('Agregar al carrito');
});

it('devuelve 404 si el producto no existe', function () {
    $this->get(route('products.show', ['slug' => 'no-existe']))->assertNotFound();
});

it('no muestra un producto desactivado', function () {
    $product = Product::factory()->inactive()->create();

    // Un producto retirado del catalogo no debe seguir accesible por su direccion.
    $this->get(route('products.show', ['slug' => $product->slug]))->assertNotFound();
});

it('avisa cuando el producto esta agotado y no ofrece agregarlo', function () {
    $product = Product::factory()->outOfStock()->create();

    $this->get(route('products.show', ['slug' => $product->slug]))
        ->assertOk()
        ->assertSee('Agotado por ahora')
        ->assertDontSee('Agregar al carrito');
});

it('avisa cuando quedan pocas piezas', function () {
    $product = Product::factory()->withStock(3)->create();

    $this->get(route('products.show', ['slug' => $product->slug]))
        ->assertOk()
        ->assertSee('Ultimas 3 piezas');
});

it('muestra el precio anterior cuando hay oferta', function () {
    $product = Product::factory()->withStock(5)->create([
        'price_cents' => 50000,
        'compare_at_price_cents' => 70000,
    ]);

    $this->get(route('products.show', ['slug' => $product->slug]))
        ->assertOk()
        ->assertSee('$700.00')
        ->assertSee('Oferta');
});

it('incluye los datos para buscadores y para compartir', function () {
    $product = Product::factory()->withStock(5)->create(['name' => 'Creatina Monohidratada']);

    $this->get(route('products.show', ['slug' => $product->slug]))
        ->assertOk()
        ->assertSee('og:title', escape: false)
        ->assertSee('rel="canonical"', escape: false)
        ->assertSee(route('products.show', ['slug' => $product->slug]));
});

it('recomienda productos de la misma categoria', function () {
    $categoria = Category::factory()->create();

    $product = Product::factory()->withStock(5)->create(['category_id' => $categoria->id]);
    $hermano = Product::factory()->withStock(5)->create([
        'category_id' => $categoria->id,
        'name' => 'Producto hermano',
    ]);
    Product::factory()->withStock(5)->create(['name' => 'De otra categoria']);

    $this->get(route('products.show', ['slug' => $product->slug]))
        ->assertOk()
        ->assertSee('Producto hermano')
        ->assertDontSee('De otra categoria')
        // No se recomienda a si mismo. Se comprueba sobre los datos y no contando
        // apariciones del nombre, que aparece de forma legitima en el titulo, el
        // encabezado y los metadatos.
        ->assertViewHas('related', fn ($related): bool => ! $related->contains('id', $product->id));
});

it('prefiere recomendar productos disponibles', function () {
    $categoria = Category::factory()->create();

    $product = Product::factory()->withStock(5)->create(['category_id' => $categoria->id]);

    // Se crean primero los agotados: si el orden no los penalizara, ocuparian los
    // primeros lugares.
    Product::factory()->count(4)->outOfStock()->create(['category_id' => $categoria->id]);
    $disponible = Product::factory()->withStock(5)->create([
        'category_id' => $categoria->id,
        'name' => 'Si hay existencias',
    ]);

    $this->get(route('products.show', ['slug' => $product->slug]))
        ->assertOk()
        ->assertSee('Si hay existencias');

    expect($disponible->is_in_stock)->toBeTrue();
});

it('el catalogo enlaza a la pagina del producto', function () {
    $product = Product::factory()->featured()->withStock(5)->create();

    $this->get('/')
        ->assertOk()
        ->assertSee(route('products.show', ['slug' => $product->slug]));
});
