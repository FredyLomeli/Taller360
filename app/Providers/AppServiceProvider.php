<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Carbon;
use App\Models\Sale;
use App\Observers\SaleObserver;
use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);
        Carbon::setLocale('es');
        Sale::observe(SaleObserver::class);

        if($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
