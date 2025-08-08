<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as BaseVerifyCsrfToken;
use App\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Config;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(BaseVerifyCsrfToken::class, VerifyCsrfToken::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Paksa skema HTTPS di lingkungan lokal
        if (app()->environment('local')) {
            URL::forceScheme('https');
        }
    }
}
