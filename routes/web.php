<?php

use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\OrderTrackingController;
use App\Http\Controllers\PaymentReceiptController;
use App\Http\Controllers\PaymentReturnController;
use App\Http\Controllers\PaymentWebhookController;
use App\Http\Controllers\PostalCodeController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\StorefrontController;
use Illuminate\Support\Facades\Route;

Route::get('/', StorefrontController::class)->name('storefront.home');
Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');

Route::get('/catalogo', CatalogController::class)->name('catalog.index');
Route::get('/producto/{slug}', [ProductController::class, 'show'])->name('products.show');

// Consulta de codigos postales del checkout. Lleva limite de peticiones porque
// es publica y se llama a cada tecla: sin el, serviria para recorrer el catalogo
// completo a fuerza de peticiones.
Route::get('/codigo-postal/{postcode}', PostalCodeController::class)
    ->whereNumber('postcode')
    ->middleware('throttle:60,1')
    ->name('postal-code.show');

/*
|--------------------------------------------------------------------------
| Pagos
|--------------------------------------------------------------------------
*/

// Vuelta del cliente desde la pantalla del proveedor. No se cree nada de lo que
// llega en la URL: el estado se pregunta al proveedor.
Route::get('/pago/retorno/{code}', [PaymentReturnController::class, 'returned'])->name('payments.return');
Route::get('/pago/cancelado/{code}', [PaymentReturnController::class, 'cancelled'])->name('payments.cancelled');

// Avisos de los proveedores. Sin CSRF porque no viene de un formulario nuestro;
// lo que autentica el aviso es la firma que verifica cada adaptador.
Route::post('/webhooks/pagos/{provider}', PaymentWebhookController::class)
    ->middleware('throttle:120,1')
    ->name('payments.webhook');

// Consulta del pedido. La ruta va firmada para que nadie vea pedidos ajenos
// probando folios.
Route::get('/pedido/{code}', [OrderTrackingController::class, 'show'])
    ->middleware('signed')
    ->name('orders.show');

Route::post('/pedido/{code}/comprobante', [PaymentReceiptController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('receipts.store');

Route::get('/comprobante/{receipt}', [PaymentReceiptController::class, 'show'])
    ->middleware('auth')
    ->name('receipts.show');

Route::view('/contacto', 'storefront.pages.contact')->name('pages.contact');
Route::view('/preguntas-frecuentes', 'storefront.pages.faq')->name('pages.faq');
Route::view('/terminos-y-condiciones', 'storefront.pages.terms')->name('pages.terms');
Route::view('/aviso-de-privacidad', 'storefront.pages.privacy')->name('pages.privacy');
Route::view('/politica-de-envios', 'storefront.pages.shipping')->name('pages.shipping');
Route::view('/cambios-cancelaciones-y-devoluciones', 'storefront.pages.returns')->name('pages.returns');
