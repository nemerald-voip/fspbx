<?php

namespace App\Providers;

use Inertia\Inertia;
use Livewire\Livewire;
use App\Models\Devices;
use App\Models\IvrMenus;
use App\Models\Extensions;
use App\Models\RingGroups;
use App\Models\Voicemails;
use Laravel\Horizon\Horizon;
use Laravel\Sanctum\Sanctum;
use App\Observers\ExtensionObserver;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use App\Models\Sanctum\PersonalAccessToken;
use App\Livewire\CallDetailRecords\CdrTable;
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

        Livewire::component('cdr-table', CdrTable::class);

        Vite::useHotFile(storage_path('vite.hot')) // Customize the "hot" file...
            ->useBuildDirectory('storage/vite') // Customize the build directory...
            ->withEntryPoints([
                'resources/js/app.js'
            ]);


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

        Extensions::observe(ExtensionObserver::class);

        Builder::macro('orWhereLike', function (string $column, string $search) {
            return $this->orWhere($column, 'ILIKE', '%' . trim($search) . '%');
        });

        Builder::macro('andWhereLike', function (string $column, string $search) {
            return $this->where($column, 'ILIKE', '%' . trim($search) . '%');
        });
        /* Note: Temporary commented. Not removed because it can be used in next updates
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
*/
        Validator::extend('ExtensionExists', function ($attribute, $value, $parameters, $validator) {
            if (!isset($parameters[0])) {
                return false;
            }
            $domain = $parameters[0];
            if ($value == '0') {
                // Bypass validation if the extension is 0. Means we have not chosen an extension due
                // the option is partially optional
                return true;
            } else {
                $found = false;
                if (Extensions::where('extension', $value)->where('domain_uuid', $domain)->first()) {
                    $found = true;
                }
                if (IvrMenus::where('ivr_menu_extension', $value)->where('domain_uuid', $domain)->first()) {
                    $found = true;
                }
                if (RingGroups::where('ring_group_extension', $value)->where('domain_uuid', $domain)->first()) {
                    $found = true;
                }
                if (Voicemails::where('voicemail_id', $value)->where('domain_uuid', $domain)->first()) {
                    $found = true;
                }

                return $found;
            }
        });

        Validator::extend('RingGroupUnique', function ($attribute, $value, $parameters, $validator) {
            if (!isset($parameters[0])) {
                return false;
            }
            $domain = $parameters[0];
            if (Extensions::where('extension', $value)->where('domain_uuid', $domain)->first()) {
                return false;
            }

            if (isset($parameters[1]) && !empty($parameters[1])) {
                if (RingGroups::where('ring_group_extension', $value)->where('ring_group_uuid', '!=', $parameters[1])->where('domain_uuid', $domain)->first()) {
                    return false;
                }
            } else {
                if (RingGroups::where('ring_group_extension', $value)->where('domain_uuid', $domain)->first()) {
                    return false;
                }
            }

            if (Voicemails::where('voicemail_id', $value)->where('domain_uuid', $domain)->first()) {
                return false;
            }

            return true;
        });
        /*
        Validator::extend('DeviceMacAddressNotExists', function ($attribute, $value, $parameters, $validator) {
            $value = str_replace([':', '-', '.'], '', $value);
            $value = strtolower($value);
            return !Devices::where('device_address', $value)->exists();
        });
*/

        Password::defaults(function () {
            $rule = Password::min(8)
                ->letters()
                ->mixedCase()
                ->numbers()
                ->symbols()
                ->uncompromised();

            return $rule;
        });
    }
}
