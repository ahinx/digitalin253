<?php

use App\Http\Controllers\MagicLinkController;
use App\Http\Controllers\MidtransWebhookController;
use App\Http\Controllers\OrderController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

use App\Http\Controllers\ShopController;
use App\Http\Controllers\ThankYouController;

// Etalase Produk
Route::get('/', [ShopController::class, 'index'])->name('shop.index');
Route::get('/shop', [ShopController::class, 'index']); // Alias

// Keranjang
Route::post('/cart/add', [ShopController::class, 'addToCart'])->name('shop.addToCart');
Route::get('/cart', [ShopController::class, 'viewCart'])->name('shop.cart');
Route::post('/cart/update', [ShopController::class, 'updateCart'])->name('shop.cart.update');

Route::post('/voucher/check', [ShopController::class, 'checkVoucher'])->name('shop.voucher.check');

// Checkout (AJAX menghasilkan Snap Token)
Route::post('/checkout', [ShopController::class, 'checkout'])->name('shop.checkout');

// Halaman Terima Kasih setelah bayar
Route::get('/thank-you', [ShopController::class, 'thankYou'])->name('shop.thankyou');

// Magic link download
Route::get('/magic-link/{token}', [MagicLinkController::class, 'handle'])->name('magic.link');


Route::get('/payment/{order}', [ShopController::class, 'paymentLink'])->name('shop.paymentLink');

// Rute baru untuk Lacak Pesanan
Route::get('/track-order', [ShopController::class, 'trackOrder'])->name('shop.trackOrder');

// Untuk memproses pencarian
Route::post('/track-order', [ShopController::class, 'trackOrder'])->name('shop.trackOrder.post');
