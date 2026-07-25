<?php

use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\PostalCodeController;
use App\Http\Controllers\StorefrontController;
use Illuminate\Support\Facades\Route;

Route::get('/', StorefrontController::class)->name('storefront.home');
Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');

// Consulta de codigos postales del checkout. Lleva limite de peticiones porque
// es publica y se llama a cada tecla: sin el, serviria para recorrer el catalogo
// completo a fuerza de peticiones.
Route::get('/codigo-postal/{postcode}', PostalCodeController::class)
    ->whereNumber('postcode')
    ->middleware('throttle:60,1')
    ->name('postal-code.show');

Route::view('/contacto', 'storefront.pages.contact')->name('pages.contact');
Route::view('/preguntas-frecuentes', 'storefront.pages.faq')->name('pages.faq');
Route::view('/terminos-y-condiciones', 'storefront.pages.terms')->name('pages.terms');
Route::view('/aviso-de-privacidad', 'storefront.pages.privacy')->name('pages.privacy');
Route::view('/politica-de-envios', 'storefront.pages.shipping')->name('pages.shipping');
Route::view('/cambios-cancelaciones-y-devoluciones', 'storefront.pages.returns')->name('pages.returns');
