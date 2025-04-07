<?php

namespace App\Observers;

use App\Models\EmergencyCallMember;
use Illuminate\Support\Facades\Cache;

class EmergencyCallMemberObserver
{
    public function created(EmergencyCallMember $member): void
    {
        Cache::forget('emergency_calls');
    }

    public function updated(EmergencyCallMember $member): void
    {
        Cache::forget('emergency_calls');
    }

    public function deleted(EmergencyCallMember $member): void
    {
        Cache::forget('emergency_calls');
    }

    public function restored(EmergencyCallMember $member): void
    {
        Cache::forget('emergency_calls');
    }

    public function forceDeleted(EmergencyCallMember $member): void
    {
        Cache::forget('emergency_calls');
    }
}

