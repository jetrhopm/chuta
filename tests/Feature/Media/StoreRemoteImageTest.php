<?php

use App\Domain\Media\Actions\StoreRemoteImage;
use App\Domain\Media\Exceptions\RemoteImageFailed;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

it('descarga una imagen y la guarda con la extension que le corresponde', function () {
    Http::fake(['*' => Http::response(pngDePrueba(), 200, ['Content-Type' => 'image/png'])]);

    $path = app(StoreRemoteImage::class)->handle('https://ejemplo.test/foto.jpg');

    // La extension sale del contenido real, no de la URL: ahi decia .jpg pero los
    // bytes son PNG.
    expect($path)->toEndWith('.png')
        ->and($path)->toStartWith('products/')
        ->and(Storage::disk('public')->exists($path))->toBeTrue();
});

it('no guarda dos veces la misma imagen', function () {
    Http::fake(['*' => Http::response(pngDePrueba(), 200)]);

    $accion = app(StoreRemoteImage::class);

    $primera = $accion->handle('https://ejemplo.test/uno.png');
    $segunda = $accion->handle('https://ejemplo.test/dos.png');

    // Mismo contenido, mismo archivo: dos productos que comparten foto no la
    // duplican en disco.
    expect($primera)->toBe($segunda)
        ->and(Storage::disk('public')->files('products'))->toHaveCount(1);
});

it('rechaza un archivo que no es una imagen', function () {
    Http::fake(['*' => Http::response('<html>pagina de error</html>', 200, ['Content-Type' => 'image/png'])]);

    // El encabezado miente; lo que decide son los bytes.
    expect(fn () => app(StoreRemoteImage::class)->handle('https://ejemplo.test/roto.png'))
        ->toThrow(RemoteImageFailed::class);

    expect(Storage::disk('public')->files('products'))->toBeEmpty();
});

it('rechaza una respuesta de error del servidor', function () {
    Http::fake(['*' => Http::response('no existe', 404)]);

    expect(fn () => app(StoreRemoteImage::class)->handle('https://ejemplo.test/falta.png'))
        ->toThrow(RemoteImageFailed::class, 'El servidor respondio 404.');
});

it('rechaza una respuesta vacia', function () {
    Http::fake(['*' => Http::response('', 200)]);

    expect(fn () => app(StoreRemoteImage::class)->handle('https://ejemplo.test/vacia.png'))
        ->toThrow(RemoteImageFailed::class, 'La respuesta llego vacia.');
});

it('rechaza una direccion que no es una URL', function () {
    expect(fn () => app(StoreRemoteImage::class)->handle('no-es-una-url'))
        ->toThrow(RemoteImageFailed::class);
});

it('acepta direcciones con caracteres internacionales en la ruta', function () {
    Http::fake(['*' => Http::response(pngDePrueba(), 200)]);

    $path = app(StoreRemoteImage::class)->handle('https://ejemplo.test/뉴_플래티넘_하이드로_웨이.png');

    expect($path)->toStartWith('products/')
        ->and(Storage::disk('public')->exists($path))->toBeTrue();
});

it('guarda los banners en su propia carpeta', function () {
    Http::fake(['*' => Http::response(pngDePrueba(), 200)]);

    $path = app(StoreRemoteImage::class)->handle('https://ejemplo.test/banner.jpg', 'banners');

    expect($path)->toStartWith('banners/');
});
