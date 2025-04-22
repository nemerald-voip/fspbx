<?php

namespace App\Observers;

use App\Models\EmergencyCall;
use Illuminate\Support\Facades\Cache;

class EmergencyCallObserver
{
    public function created(EmergencyCall $emergencyCall): void
    {
        Cache::forget('emergency_calls');
    }

    public function updated(EmergencyCall $emergencyCall): void
    {
        Cache::forget('emergency_calls');
    }

    public function deleted(EmergencyCall $emergencyCall): void
    {
        Cache::forget('emergency_calls');
    }

    public function restored(EmergencyCall $emergencyCall): void
    {
        Cache::forget('emergency_calls');
    }

    public function forceDeleted(EmergencyCall $emergencyCall): void
    {
        Cache::forget('emergency_calls');
    }
}

