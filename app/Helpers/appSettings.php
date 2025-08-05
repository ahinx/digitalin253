<?php

use App\Models\AppSetting;

if (!function_exists('setting')) {
    function setting(string $key, mixed $default = null): mixed
    {
        static $settings = null;

        if ($settings === null) {
            $settings = AppSetting::pluck('value', 'key')->toArray();
        }

        return $settings[$key] ?? $default;
    }
}

if (!function_exists('appSettings')) {
    function appSettings(): array
    {
        static $settings = null;

        if ($settings === null) {
            $settings = AppSetting::pluck('value', 'key')->toArray();
        }

        return $settings;
    }
}

// ⬇️ Tambahkan fungsi ini ⬇️
if (! function_exists('settings')) {
    /**
     * Simpan array [$key => $value] ke tabel app_settings.
     */
    function settings(array $data): void
    {
        foreach ($data as $key => $value) {
            AppSetting::updateOrCreate(
                ['key'   => $key],
                ['value' => $value],
            );
        }

        // (opsional) kalau pakai cache:
        cache()->forget('app_settings');
    }
}
