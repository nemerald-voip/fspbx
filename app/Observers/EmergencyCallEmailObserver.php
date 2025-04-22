<?php

namespace App\Observers;

use App\Models\EmergencyCallEmail;
use Illuminate\Support\Facades\Cache;

class EmergencyCallEmailObserver
{
    public function created(EmergencyCallEmail $email): void
    {
        Cache::forget('emergency_calls');
    }

    public function updated(EmergencyCallEmail $email): void
    {
        Cache::forget('emergency_calls');
    }

    public function deleted(EmergencyCallEmail $email): void
    {
        Cache::forget('emergency_calls');
    }

    public function restored(EmergencyCallEmail $email): void
    {
        Cache::forget('emergency_calls');
    }

    public function forceDeleted(EmergencyCallEmail $email): void
    {
        Cache::forget('emergency_calls');
    }
}


