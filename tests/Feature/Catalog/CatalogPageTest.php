<?php

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;

it('muestra el catalogo completo', function () {
    Product::factory()->count(3)->create();

    $this->get(route('catalog.index'))
        ->assertOk()
        ->assertSee('Catalogo')
        ->assertSee('3 productos');
});

it('busca por termino desde la direccion', function () {
    Product::factory()->create(['name' => 'Proteina Whey Isolada']);
    Product::factory()->create(['name' => 'Creatina Monohidratada']);

    $this->get(route('catalog.index', ['q' => 'whey']))
        ->assertOk()
        ->assertSee('Resultados para')
        ->assertSee('Proteina Whey Isolada')
        ->assertDontSee('Creatina Monohidratada');
});

it('filtra por categoria desde la direccion', function () {
    $categoria = Category::factory()->create();
    Product::factory()->create(['category_id' => $categoria->id, 'name' => 'Dentro de la categoria']);
    Product::factory()->create(['name' => 'Fuera de la categoria']);

    $this->get(route('catalog.index', ['categoria' => $categoria->id]))
        ->assertOk()
        ->assertSee('Dentro de la categoria')
        ->assertDontSee('Fuera de la categoria');
});

it('filtra solo ofertas', function () {
    Product::factory()->create([
        'name' => 'Con descuento',
        'price_cents' => 50000,
        'compare_at_price_cents' => 70000,
    ]);
    Product::factory()->create(['name' => 'Sin descuento', 'price_cents' => 50000]);

    $this->get(route('catalog.index', ['ofertas' => 1]))
        ->assertOk()
        ->assertSee('Con descuento')
        ->assertDontSee('Sin descuento');
});

it('explica cuando no hay resultados y ofrece una salida', function () {
    Product::factory()->create(['name' => 'Creatina']);

    $this->get(route('catalog.index', ['q' => 'inexistente']))
        ->assertOk()
        ->assertSee('No encontramos nada con esos filtros')
        ->assertSee('Ver todo el catalogo');
});

it('ofrece limpiar los filtros solo cuando hay alguno activo', function () {
    Product::factory()->create();

    $this->get(route('catalog.index'))
        ->assertOk()
        ->assertDontSee('Limpiar filtros');

    $this->get(route('catalog.index', ['q' => 'algo']))
        ->assertOk()
        ->assertSee('Limpiar filtros');
});

it('solo ofrece filtros de categorias y marcas con productos', function () {
    $conProductos = Category::factory()->create(['name' => 'Categoria con productos']);
    Category::factory()->create(['name' => 'Categoria vacia']);

    $marcaConProductos = Brand::factory()->create(['name' => 'Marca con productos']);
    Brand::factory()->create(['name' => 'Marca vacia']);

    Product::factory()->create([
        'category_id' => $conProductos->id,
        'brand_id' => $marcaConProductos->id,
    ]);

    // Ofrecer un filtro que no devuelve nada es una via muerta.
    $this->get(route('catalog.index'))
        ->assertOk()
        ->assertSee('Categoria con productos')
        ->assertDontSee('Categoria vacia')
        ->assertSee('Marca con productos')
        ->assertDontSee('Marca vacia');
});

it('el buscador de la portada lleva al catalogo', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee(route('catalog.index'));
});

it('las paginas de error usan el diseno de la tienda y no exponen detalles', function () {
    $contenido = $this->get('/ruta-que-no-existe')
        ->assertNotFound()
        ->assertSee('No encontramos esa pagina')
        ->assertSee('Ir al inicio')
        ->assertSee('Ver catalogo')
        ->getContent();

    // Nunca detalles internos hacia el cliente.
    expect($contenido)->not->toContain('Exception')
        ->and($contenido)->not->toContain('vendor/laravel')
        ->and($contenido)->not->toContain('SQL');
});

it('las paginas de error piden no ser indexadas', function () {
    $this->get('/ruta-que-no-existe')
        ->assertNotFound()
        ->assertSee('name="robots"', escape: false);
});
