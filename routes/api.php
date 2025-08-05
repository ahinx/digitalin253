<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MidtransWebhookController;

// Route::post('/midtrans/webhook', [MidtransWebhookController::class, 'handle']);

// terima GET (untuk test) dan POST (untuk notifikasi sebenarnya)
Route::match(['get', 'post', 'head'], 'midtrans/webhook', [MidtransWebhookController::class, 'handle']);
