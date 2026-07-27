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
 *
 * Only session-backed requests are localized -- the web app and its
 * cookie-authenticated SPA API, whose responses are shown directly to a
 * signed-in user. Stateless requests (bearer-token or shared-secret
 * machine/webhook calls, which have no session) are intentionally left in
 * the source language: like Stripe's developer API, a machine-facing
 * endpoint stays in one language and the integrator localizes for its own
 * users. This also keeps webhook output from following the system default
 * language if an admin sets that to something other than English.
 */
class SetApplicationLocale
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->hasSession() && $request->session()->isStarted()) {
            $requested = session('domain.language.code') ?? get_domain_setting('language');

            App::setLocale(app(LocaleRegistry::class)->resolve($requested));
        }

        return $next($request);
    }
}
