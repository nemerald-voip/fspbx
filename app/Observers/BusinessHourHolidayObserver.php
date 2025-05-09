<?php

namespace App\Observers;

use App\Models\BusinessHourHoliday;
use App\Models\BusinessHour;
use App\Http\Controllers\BusinessHoursController;

class BusinessHourHolidayObserver
{
    public function created(BusinessHourHoliday $holiday)
    {
        $this->regenerate($holiday);
    }

    public function updated(BusinessHourHoliday $holiday)
    {
        $this->regenerate($holiday);
    }

    public function deleted(BusinessHourHoliday $holiday)
    {
        $this->regenerate($holiday);
    }

    protected function regenerate(BusinessHourHoliday $holiday): void
    {
        $bh = BusinessHour::find($holiday->business_hour_uuid);
        if (! $bh) {
            return;
        }
        // call your existing dialplan generator
        app(BusinessHoursController::class)->generateDialPlanXML($bh);
    }
}
