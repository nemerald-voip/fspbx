<?php

namespace App\Http\Controllers;

use App\Models\UserSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Session;

class ThemeController extends Controller
{
    /**
     * Persist the authenticated user's dark/light mode preference.
     *
     * Stored per-user in v_user_settings (category=theme, name=mode) so it
     * follows the user across devices, and mirrored into the session so the
     * root blade can apply the .dark class server-side on the next full load.
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'theme' => 'required|in:light,dark',
        ]);

        $user = $request->user();

        $setting = UserSetting::where('user_uuid', $user->user_uuid)
            ->where('user_setting_category', 'theme')
            ->where('user_setting_name', 'mode')
            ->first();

        if (!$setting) {
            $setting = new UserSetting();
            $setting->domain_uuid = $user->domain_uuid;
            $setting->user_uuid = $user->user_uuid;
            $setting->user_setting_category = 'theme';
            $setting->user_setting_subcategory = null;
            $setting->user_setting_name = 'mode';
            $setting->user_setting_enabled = true;
        }

        $setting->user_setting_value = $validated['theme'];
        $setting->save();

        Session::put('theme', $validated['theme']);

        // Browser-level cookie so pre-auth screens (e.g. the login page, where
        // there is no user session) can reflect this browser's last choice.
        // Hardened with Secure + SameSite=Lax; kept httpOnly with a 1-year life.
        Cookie::queue(cookie('theme', $validated['theme'], 60 * 24 * 365, null, null, true, true, false, 'lax'));

        return response()->json([
            'status' => 'success',
            'theme'  => $validated['theme'],
        ]);
    }
}
