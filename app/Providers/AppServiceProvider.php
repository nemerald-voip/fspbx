<?php

namespace App\Providers;

use App\Models\Extensions;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Horizon\Horizon;
use Laravel\Sanctum\Sanctum;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\ServiceProvider;
use App\Models\Sanctum\PersonalAccessToken;
use Illuminate\Database\Eloquent\Builder;
use Propaganistas\LaravelPhone\Validation\Phone;

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

        Builder::macro('orWhereLike', function(string $column, string $search) {
            return $this->orWhere($column, 'ILIKE', '%'.trim($search).'%');
        });

        Builder::macro('andWhereLike', function(string $column, string $search) {
            return $this->where($column, 'ILIKE', '%'.trim($search).'%');
        });

        Validator::extend('PhoneOrExtension', function ($attribute, $value, $parameters, $validator) {
            $phoneFormat = 'US';
            if(isset($parameters[0])) {
                $phoneFormat = $parameters[0];
            }
            if(strlen($value) <= 5) {
                $builder = Extensions::where('extension', $value);
                if(isset($parameters[1])) {
                    $builder->where('domain_uuid', $parameters[1]);
                }
                return (bool)$builder->first();
            } else {
                return (new Phone())->validate($attribute, $value, [$phoneFormat], $validator);
            }
        });
    }
}
