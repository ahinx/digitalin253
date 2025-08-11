<?php

use App\Http\Controllers\MagicLinkController;
use App\Http\Controllers\MidtransWebhookController;
use App\Http\Controllers\OrderController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

use App\Http\Controllers\ShopController;

// Etalase Produk
Route::get('/', [ShopController::class, 'index'])->name('shop.index');
Route::get('/shop', [ShopController::class, 'index']); // Alias

// Rute untuk detail produk
// Route::get('/product/{slug}', [ShopController::class, 'show'])->name('shop.show'); // <<< Rute untuk detail produk dengan slug manual
Route::get('/product/{product}', [ShopController::class, 'show'])->name('shop.show'); // <<< Rute untuk detail produk dengan slug otomatis
// Route::get('/product/{product:slug}', [ShopController::class, 'show'])->name('shop.show');



// Keranjang
Route::post('/cart/add', [ShopController::class, 'addToCart'])->name('shop.addToCart');
Route::get('/cart', [ShopController::class, 'viewCart'])->name('shop.cart');
Route::post('/cart/update', [ShopController::class, 'updateCart'])->name('shop.cart.update'); // Ini sepertinya tidak ada di ShopController yang sekarang, bisa dihapus jika tidak digunakan

// Voucher
Route::post('/voucher/apply', [ShopController::class, 'applyVoucher'])->name('shop.applyVoucher'); // <<< Rute baru untuk menerapkan voucher
Route::post('/voucher/remove', [ShopController::class, 'removeVoucher'])->name('shop.removeVoucher'); // <<< Rute baru untuk menghapus voucher
// Route::post('/voucher/check', [ShopController::class, 'checkVoucher'])->name('shop.voucher.check'); // Ini sepertinya tidak ada di ShopController yang sekarang, bisa dihapus jika tidak digunakan

// Checkout (AJAX menghasilkan Snap Token)
Route::post('/checkout', [ShopController::class, 'checkout'])->name('shop.checkout');

// Halaman Terima Kasih setelah bayar
Route::get('/thank-you', [ShopController::class, 'thankYou'])->name('shop.thankyou');

// Magic link download
Route::get('/magic-link/{token}', [MagicLinkController::class, 'handle'])->name('magic.link');

// Payment dari WhatsApp (redirect ke lacak pesanan)
Route::get('/payment/{order}', [ShopController::class, 'paymentLink'])->name('shop.paymentLink');

// Lacak Pesanan
Route::get('/track-order', [ShopController::class, 'trackOrder'])->name('shop.trackOrder');
// Untuk memproses pencarian (dengan throttle)
Route::post('/track-order', [ShopController::class, 'trackOrder'])->name('shop.trackOrder.post')->middleware('throttle:10,1'); // 10 attempts per minute
