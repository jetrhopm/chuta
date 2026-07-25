<?php

use App\Domain\Storefront\StorefrontContentRepository;
use App\Models\Product;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');

    app(StorefrontContentRepository::class)->saveBanners([
        ['image' => 'https://sitio-anterior.test/banner-1.jpg', 'alt' => 'Uno', 'url' => '#productos'],
        ['image' => 'https://sitio-anterior.test/banner-2.jpg', 'alt' => 'Dos', 'url' => '#productos'],
    ]);
});

it('descarga las imagenes de producto y reescribe sus rutas', function () {
    Http::fake(['*' => Http::response(pngDePrueba(), 200)]);

    $product = Product::factory()->create([
        'image_path' => 'https://sitio-anterior.test/producto.jpg',
    ]);

    $this->artisan('media:localize')->assertSuccessful();

    $product->refresh();

    expect($product->image_path)->toStartWith('products/')
        ->and($product->image_path)->not->toContain('sitio-anterior.test')
        ->and(Storage::disk('public')->exists($product->image_path))->toBeTrue();
});

it('deja la imagen servida desde este servidor', function () {
    Http::fake(['*' => Http::response(pngDePrueba(), 200)]);

    $product = Product::factory()->create([
        'image_path' => 'https://sitio-anterior.test/producto.jpg',
    ]);

    $this->artisan('media:localize')->assertSuccessful();

    // La direccion publica sale del enlace simbolico de storage, no del sitio
    // anterior.
    expect($product->fresh()->image_url)
        ->toContain('/storage/products/')
        ->and($product->fresh()->image_url)->not->toContain('sitio-anterior.test');
});

it('tambien descarga los banners', function () {
    Http::fake(['*' => Http::response(pngDePrueba(), 200)]);

    $this->artisan('media:localize')->assertSuccessful();

    $banners = app(StorefrontContentRepository::class)->banners();

    expect($banners)->toHaveCount(2);

    foreach ($banners as $banner) {
        expect($banner['image'])->toStartWith('banners/')
            ->and(Storage::disk('public')->exists($banner['image']))->toBeTrue();
    }
});

it('conserva el texto alternativo y el enlace del banner', function () {
    Http::fake(['*' => Http::response(pngDePrueba(), 200)]);

    $this->artisan('media:localize')->assertSuccessful();

    $banners = app(StorefrontContentRepository::class)->banners();

    expect($banners[0]['alt'])->toBe('Uno')
        ->and($banners[0]['url'])->toBe('#productos');
});

it('no vuelve a descargar lo que ya esta en este servidor', function () {
    Http::fake(['*' => Http::response(pngDePrueba(), 200)]);

    Product::factory()->create(['image_path' => 'https://sitio-anterior.test/producto.jpg']);

    $this->artisan('media:localize')->assertSuccessful();

    // La segunda pasada no debe pedir nada: ya no queda nada remoto.
    Http::fake(['*' => Http::response('no deberia pedirse', 500)]);

    $this->artisan('media:localize')
        ->expectsOutputToContain('No hay imagenes de producto por descargar.')
        ->assertSuccessful();
});

it('conserva la direccion original cuando la descarga falla', function () {
    Http::fake(['*' => Http::response('no existe', 404)]);

    $product = Product::factory()->create([
        'image_path' => 'https://sitio-anterior.test/roto.jpg',
    ]);

    // Falla el comando para que un despliegue se entere, pero el producto no se
    // queda sin ninguna imagen.
    $this->artisan('media:localize')->assertFailed();

    expect($product->fresh()->image_path)->toBe('https://sitio-anterior.test/roto.jpg');
});

it('un fallo suelto no detiene el resto de las descargas', function () {
    Http::fake([
        'sitio-anterior.test/roto.jpg' => Http::response('no existe', 404),
        '*' => Http::response(pngDePrueba(), 200),
    ]);

    $roto = Product::factory()->create(['image_path' => 'https://sitio-anterior.test/roto.jpg']);
    $bueno = Product::factory()->create(['image_path' => 'https://sitio-anterior.test/bueno.jpg']);

    $this->artisan('media:localize')->assertFailed();

    expect($roto->fresh()->image_path)->toStartWith('https://')
        ->and($bueno->fresh()->image_path)->toStartWith('products/');
});

it('en modo simulacion no escribe nada', function () {
    Http::fake(['*' => Http::response(pngDePrueba(), 200)]);

    $product = Product::factory()->create([
        'image_path' => 'https://sitio-anterior.test/producto.jpg',
    ]);

    $this->artisan('media:localize', ['--dry-run' => true])->assertSuccessful();

    expect($product->fresh()->image_path)->toBe('https://sitio-anterior.test/producto.jpg')
        ->and(Storage::disk('public')->allFiles())->toBeEmpty();
});

it('respeta el limite de productos por ejecucion', function () {
    Http::fake(['*' => Http::response(pngDePrueba(), 200)]);

    Product::factory()->count(3)->create([
        'image_path' => 'https://sitio-anterior.test/producto.jpg',
    ]);

    $this->artisan('media:localize', ['--limit' => 1])->assertSuccessful();

    // Permite hacer la migracion por tandas en un servidor compartido.
    expect(Product::where('image_path', 'like', 'http%')->count())->toBe(2);
});
