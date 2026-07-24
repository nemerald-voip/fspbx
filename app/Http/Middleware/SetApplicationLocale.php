<?php

namespace App\Http\Middleware;

use App\Support\Localization\LocaleRegistry;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

/**
 * Resolves the active domain's "language" setting (domain.language.code --
 * the same domain/default_settings row FusionPBX's legacy admin already
 * reads via get_domain_setting('language')) and applies it for this request.
 *
 * Locale is a per-domain setting, not a per-user preference: every user in a
 * domain sees the same language. Like every other domain setting, a change
 * takes effect on next login or an explicit "Reload Settings" action, not
 * mid-session, since v_domain_settings overrides are only re-synced to the
 * session at those points (see SettingsManagementService::reloadSessionSettings()).
 */
class SetApplicationLocale
{
    public function handle(Request $request, Closure $next)
    {
        $requested = session('domain.language.code') ?? get_domain_setting('language');

        App::setLocale(app(LocaleRegistry::class)->resolve($requested));

        return $next($request);
    }
}
