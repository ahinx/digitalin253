<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

if (!function_exists('appSettings')) {
    function appSettings(): array
    {
        return Cache::rememberForever('app_settings', function () {
            return DB::table('app_settings')
                ->pluck('value', 'key')
                ->toArray();
        });
    }
}

if (!function_exists('settings')) {
    function settings(array $data): void
    {
        foreach ($data as $key => $value) {
            DB::table('app_settings')->updateOrInsert(
                ['key' => $key],
                ['value' => is_array($value) ? json_encode($value) : $value]
            );
        }

        // Hapus cache lama dan isi ulang
        Cache::forget('app_settings');
        appSettings();
    }
}
