<?php

namespace App\Console\Commands\Updates;

use App\Models\FusionCache;
use Illuminate\Support\Facades\DB;
use Throwable;

class Update211
{
    public function apply(): bool
    {
        try {
            // Stored routes and their detail rows deliberately stay unchanged.
            DB::transaction(function () {
                DB::table('v_default_settings')
                    ->where('default_setting_category', 'dialplan')
                    ->where('default_setting_subcategory', 'destination')->delete();
                DB::table('v_domain_settings')
                    ->where('domain_setting_category', 'dialplan')
                    ->where('domain_setting_subcategory', 'destination')->delete();
            });

            $contexts = DB::table('v_dialplans')->distinct()
                ->where(function ($query) {
                    $query->where('dialplan_context', 'public')
                        ->orWhere('dialplan_context', 'like', 'public@%')
                        ->orWhere('dialplan_context', 'like', '%.public');
                })->pluck('dialplan_context')->push('public')->unique();

            $cleared = FusionCache::clear('dialplan:destination');
            foreach ($contexts as $context) {
                $contextCleared = FusionCache::clear('dialplan:'.$context);
                $childrenCleared = FusionCache::clearPattern('dialplan:'.$context.':*');
                $cleared = $cleared && $contextCleared && $childrenCleared;
            }

            if (! $cleared) {
                echo "Unable to clear all inbound dialplan caches. Check the cache backend and retry update 2.1.1.\n";
                return false;
            }

            echo "Inbound dialplan caches cleared; obsolete Destination settings removed.\n";
            echo "Existing saved routes were preserved. Save a phone number to regenerate its XML with automatic destination selection.\n";
            return true;
        } catch (Throwable $exception) {
            echo 'Update 2.1.1 failed: '.$exception->getMessage()."\n";
            return false;
        }
    }
}
