<?php

use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\StorefrontController;
use Illuminate\Support\Facades\Route;

Route::get('/', StorefrontController::class)->name('storefront.home');
Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
