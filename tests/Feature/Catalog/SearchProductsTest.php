<?php

use App\Domain\Catalog\Actions\SearchProducts;
use App\Domain\Catalog\Data\CatalogFilters;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductTag;

function buscar(array $query = [])
{
    return app(SearchProducts::class)->handle(CatalogFilters::fromQuery($query));
}

function nombresDe($paginator): array
{
    return $paginator->pluck('name')->all();
}

it('devuelve el catalogo activo sin filtros', function () {
    Product::factory()->count(3)->create();
    Product::factory()->inactive()->create();

    // Los inactivos no se muestran en la tienda.
    expect(buscar()->total())->toBe(3);
});

it('encuentra por nombre', function () {
    Product::factory()->create(['name' => 'Proteina Whey Isolada']);
    Product::factory()->create(['name' => 'Creatina Monohidratada']);

    expect(nombresDe(buscar(['q' => 'whey'])))->toBe(['Proteina Whey Isolada']);
});

it('encuentra por una parte del nombre', function () {
    Product::factory()->create(['name' => 'Proteina Gold Standard']);

    // El indice de texto completo ignora terminos parciales, asi que hay un
    // respaldo por coincidencia dentro de la misma consulta.
    expect(buscar(['q' => 'prote'])->total())->toBe(1);
});

it('encuentra por SKU', function () {
    Product::factory()->create(['name' => 'Producto uno', 'sku' => 'CHUTAMAX-3044']);
    Product::factory()->create(['name' => 'Producto dos', 'sku' => 'CHUTAMAX-9999']);

    expect(nombresDe(buscar(['q' => 'CHUTAMAX-3044'])))->toBe(['Producto uno']);
});

it('encuentra por etiqueta', function () {
    $product = Product::factory()->create(['name' => 'Proteina neutra']);
    $tag = ProductTag::create(['name' => 'Sin lactosa', 'slug' => 'sin-lactosa']);
    $product->tags()->attach($tag);

    Product::factory()->create(['name' => 'Proteina normal']);

    expect(nombresDe(buscar(['q' => 'lactosa'])))->toBe(['Proteina neutra']);
});

it('ignora un termino de una sola letra', function () {
    Product::factory()->count(3)->create();

    // Buscar "a" devolveria casi todo el catalogo y no ayuda a nadie.
    $filters = CatalogFilters::fromQuery(['q' => 'a']);

    expect($filters->hasTerm())->toBeFalse()
        ->and(buscar(['q' => 'a'])->total())->toBe(3);
});

it('filtra por categoria', function () {
    $categoria = Category::factory()->create();
    Product::factory()->create(['category_id' => $categoria->id, 'name' => 'Dentro']);
    Product::factory()->create(['name' => 'Fuera']);

    expect(nombresDe(buscar(['categoria' => (string) $categoria->id])))->toBe(['Dentro']);
});

it('filtra por marca', function () {
    $marca = Brand::factory()->create();
    Product::factory()->create(['brand_id' => $marca->id, 'name' => 'De la marca']);
    Product::factory()->create(['name' => 'De otra']);

    expect(nombresDe(buscar(['marca' => (string) $marca->id])))->toBe(['De la marca']);
});

it('filtra por varias categorias a la vez', function () {
    $una = Category::factory()->create();
    $otra = Category::factory()->create();

    Product::factory()->create(['category_id' => $una->id]);
    Product::factory()->create(['category_id' => $otra->id]);
    Product::factory()->create();

    expect(buscar(['categoria' => $una->id.','.$otra->id])->total())->toBe(2);
});

it('filtra por rango de precio en pesos', function () {
    Product::factory()->create(['price_cents' => 20000, 'name' => 'Barato']);
    Product::factory()->create(['price_cents' => 60000, 'name' => 'Medio']);
    Product::factory()->create(['price_cents' => 150000, 'name' => 'Caro']);

    // El cliente captura pesos; internamente son centavos.
    expect(nombresDe(buscar(['precio_min' => 300, 'precio_max' => 1000])))->toBe(['Medio']);
});

it('filtra solo disponibles', function () {
    Product::factory()->withStock(5)->create(['name' => 'Con existencias']);
    Product::factory()->outOfStock()->create(['name' => 'Agotado']);
    Product::factory()->untracked()->create(['name' => 'Sin control']);

    $nombres = nombresDe(buscar(['disponibles' => 1]));

    // Los que no se llevan por existencias siempre estan disponibles.
    expect($nombres)->toContain('Con existencias')
        ->and($nombres)->toContain('Sin control')
        ->and($nombres)->not->toContain('Agotado');
});

it('filtra solo ofertas', function () {
    Product::factory()->create(['price_cents' => 50000, 'compare_at_price_cents' => 70000, 'name' => 'En oferta']);
    Product::factory()->create(['price_cents' => 50000, 'name' => 'Precio normal']);
    // Un precio anterior que no es mayor no es una oferta de verdad.
    Product::factory()->create(['price_cents' => 50000, 'compare_at_price_cents' => 50000, 'name' => 'Falsa oferta']);

    expect(nombresDe(buscar(['ofertas' => 1])))->toBe(['En oferta']);
});

it('ordena por precio ascendente y descendente', function () {
    Product::factory()->create(['price_cents' => 30000, 'name' => 'Tres']);
    Product::factory()->create(['price_cents' => 10000, 'name' => 'Uno']);
    Product::factory()->create(['price_cents' => 20000, 'name' => 'Dos']);

    expect(nombresDe(buscar(['orden' => 'price_asc'])))->toBe(['Uno', 'Dos', 'Tres'])
        ->and(nombresDe(buscar(['orden' => 'price_desc'])))->toBe(['Tres', 'Dos', 'Uno']);
});

it('ordena por nombre', function () {
    Product::factory()->create(['name' => 'Zinc']);
    Product::factory()->create(['name' => 'Aminoacidos']);

    expect(nombresDe(buscar(['orden' => 'name'])))->toBe(['Aminoacidos', 'Zinc']);
});

it('pone los destacados primero al ordenar por relevancia', function () {
    Product::factory()->create(['name' => 'Aaa normal']);
    Product::factory()->featured()->create(['name' => 'Zzz destacado']);

    expect(nombresDe(buscar()))->toBe(['Zzz destacado', 'Aaa normal']);
});

it('descarta un orden inventado', function () {
    $filters = CatalogFilters::fromQuery(['orden' => 'drop table']);

    // Solo se aceptan los ordenes conocidos: el valor entra en la consulta.
    expect($filters->sort)->toBe('relevance');
});

it('combina busqueda con filtros', function () {
    $categoria = Category::factory()->create();

    Product::factory()->create([
        'category_id' => $categoria->id,
        'name' => 'Proteina Whey Barata',
        'price_cents' => 30000,
    ]);
    Product::factory()->create([
        'category_id' => $categoria->id,
        'name' => 'Proteina Whey Cara',
        'price_cents' => 200000,
    ]);
    Product::factory()->create(['name' => 'Creatina', 'price_cents' => 30000]);

    $resultado = buscar([
        'q' => 'whey',
        'categoria' => (string) $categoria->id,
        'precio_max' => 500,
    ]);

    expect(nombresDe($resultado))->toBe(['Proteina Whey Barata']);
});

it('conserva los filtros en los enlaces de paginacion', function () {
    Product::factory()->count(30)->create(['name' => 'Proteina de prueba']);

    $resultado = app(SearchProducts::class)->handle(
        CatalogFilters::fromQuery(['q' => 'proteina', 'orden' => 'name']),
        perPage: 10,
    );

    // Sin esto, pasar a la pagina dos perderia la busqueda.
    expect($resultado->url(2))->toContain('q=proteina')
        ->and($resultado->url(2))->toContain('orden=name');
});

it('reconstruye la direccion solo con los filtros activos', function () {
    $filters = CatalogFilters::fromQuery([
        'q' => 'whey',
        'categoria' => '5',
        'precio_min' => '',
        'disponibles' => 1,
        'orden' => 'relevance',
    ]);

    $query = $filters->toQuery();

    expect($query)->toHaveKey('q')
        ->and($query)->toHaveKey('categoria')
        ->and($query)->toHaveKey('disponibles')
        // Los vacios y el orden por omision no se arrastran en la direccion.
        ->and($query)->not->toHaveKey('precio_min')
        ->and($query)->not->toHaveKey('orden');
});
