<?php

namespace Database\Seeders;

use App\Services\SwitchModuleService;
use Illuminate\Database\Seeder;

class FreeswitchModulesSeeder extends Seeder
{
    /** Initialize installed modules before the first FreeSWITCH startup. */
    public function run(): void
    {
        app(SwitchModuleService::class)->initializeForInstallation();
    }
}
