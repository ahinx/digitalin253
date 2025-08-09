<?php

namespace App\Services;

use Midtrans\Config as MidtransConfig;

class MidtransService
{
    /**
     * Mengatur konfigurasi Midtrans SDK berdasarkan pengaturan aplikasi.
     *
     * @return void
     */
    public static function configure(): void
    {
        // Pastikan helper 'setting' tersedia (diasumsikan sudah dimuat via composer.json files)
        MidtransConfig::$serverKey    = setting('midtrans_server_key', config('services.midtrans.server_key'));
        MidtransConfig::$clientKey    = setting('midtrans_client_key', config('services.midtrans.client_key'));
        // Midtrans::$isProduction harus boolean, jadi konversi string 'production'/'sandbox'
        MidtransConfig::$isProduction = setting('midtrans_mode', config('services.midtrans.is_production') ? 'production' : 'sandbox') === 'production';
        MidtransConfig::$isSanitized  = true;
        MidtransConfig::$is3ds        = true;
    }
}
