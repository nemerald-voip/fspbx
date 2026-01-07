<?php

namespace App\Providers;

use App\Models\User;
use App\Models\Domain;
use App\Models\Devices;
use App\Models\Extensions;
use App\Models\RingGroups;
use Laravel\Horizon\Horizon;
use Laravel\Sanctum\Sanctum;
use App\Models\EmergencyCall;
use App\Observers\UserObserver;
use App\Observers\DeviceObserver;
use App\Observers\DomainObserver;
use App\Models\EmergencyCallEmail;
use App\Models\BusinessHourHoliday;
use App\Models\EmergencyCallMember;
use App\Models\DomainGroupRelations;
use App\Observers\ExtensionObserver;
use App\Observers\RingGroupObserver;
use App\Services\RingotelApiService;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Vite;
use App\Services\PolycomCloudProvider;
use App\Models\CallTranscriptionPolicy;
use Illuminate\Support\ServiceProvider;
use App\Observers\EmergencyCallObserver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Password;
use App\Models\UserDomainGroupPermissions;
use App\Models\Sanctum\PersonalAccessToken;
use App\Observers\EmergencyCallEmailObserver;
use App\Observers\BusinessHourHolidayObserver;
use App\Observers\EmergencyCallMemberObserver;
use App\Models\CallTranscriptionProviderConfig;
use App\Observers\DomainGroupRelationsObserver;
use App\Observers\CallTranscriptionPolicyObserver;
use App\Observers\UserDomainGroupPermissionsObserver;
use App\Observers\CallTranscriptionProviderConfigObserver;


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

        Vite::useHotFile(storage_path('vite.hot')) // Customize the "hot" file...
            ->useBuildDirectory('storage/vite') // Customize the build directory...
            ->withEntryPoints([
                'resources/js/app.js'
            ]);


        // Ringotel
        Http::macro('ringotel', function () {
            $service = app(RingotelApiService::class);
            return Http::withHeaders([
                'Authorization' => 'Bearer ' . $service->getRingotelApiToken(),
            ])->baseUrl(config('ringotel.url', 'https://shell.ringotel.co'));
        });

        // Ringotel API
        Http::macro('ringotel_api', function () {
            $service = app(RingotelApiService::class);
            return Http::withHeaders([
                'Authorization' => 'Bearer ' . $service->getRingotelApiToken(),
            ])->baseUrl(config('ringotel.api_url', 'https://shell.ringotel.co/api'));
        });

        Http::macro('polycom', function () {
            $service = app(PolycomCloudProvider::class);
            return Http::withHeaders([
                'API-KEY' => $service->getApiToken(),
            ])->baseUrl(config('ztp.polycom.api_url', 'https://api.ztp.poly.com/v1'));
        });

        Horizon::auth(function ($request) {
            // Always show admin if local development
            if (env('APP_ENV') == 'local') {
                return true;
            }
        });

        User::observe(UserObserver::class);
        UserDomainGroupPermissions::observe(UserDomainGroupPermissionsObserver::class);
        Extensions::observe(ExtensionObserver::class);
        EmergencyCall::observe(EmergencyCallObserver::class);
        EmergencyCallMember::observe(EmergencyCallMemberObserver::class);
        EmergencyCallEmail::observe(EmergencyCallEmailObserver::class);
        BusinessHourHoliday::observe(BusinessHourHolidayObserver::class);
        Devices::observe(DeviceObserver::class);
        CallTranscriptionPolicy::observe(CallTranscriptionPolicyObserver::class);
        CallTranscriptionProviderConfig::observe(CallTranscriptionProviderConfigObserver::class);
        Domain::observe(DomainObserver::class);    
        DomainGroupRelations::observe(DomainGroupRelationsObserver::class);
        RingGroups::observe(RingGroupObserver::class);


        Builder::macro('orWhereLike', function (string $column, string $search) {
            return $this->orWhere($column, 'ILIKE', '%' . trim($search) . '%');
        });

        Builder::macro('andWhereLike', function (string $column, string $search) {
            return $this->where($column, 'ILIKE', '%' . trim($search) . '%');
        });


        Password::defaults(function () {
            $rule = Password::min(10)
                ->letters()
                ->mixedCase()
                ->numbers()
                ->symbols()
                ->uncompromised();

            return $rule;
        });
    }
}
