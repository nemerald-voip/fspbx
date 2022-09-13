<?php

namespace App\Providers;

use Laravel\Horizon\Horizon;
use Laravel\Sanctum\Sanctum;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\ServiceProvider;
use App\Models\Sanctum\PersonalAccessToken;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        Sanctum::ignoreMigrations();
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        Paginator::useBootstrap();
        
        // Ringotel
        Http::macro('ringotel', function () {
            return Http::withHeaders([
                'Authorization' => 'Bearer ' . env('RINGOTEL_TOKEN'),
            ])->baseUrl(env('RINGOTEL_URL'));
        });

        // Ringotel API
        Http::macro('ringotel_api', function () {
            return Http::withHeaders([
                'Authorization' => 'Bearer ' . env('RINGOTEL_TOKEN'),
            ])->baseUrl(env('RINGOTEL_API_URL'));
        });


        Horizon::auth(function ($request) {
            // Always show admin if local development
            if (env('APP_ENV') == 'local') {
                return true;
            }
        });

    }
}
