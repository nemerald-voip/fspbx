<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\DeviceVendor;
use Illuminate\Support\Str;

class DeviceVendorsSeeder extends Seeder
{
    /**
     * Seed the v_device_vendors table with initial data.
     *
     * @return void
     */
    public function run()
    {
        $vendors = [
            [
                'name'        => 'dinstar',
                'enabled'     => 'true',
                'description' => '',
            ],
            // Add more vendors here as needed
        ];

        foreach ($vendors as $vendor) {
            $existing = DeviceVendor::where('name', $vendor['name'])->first();
            if (!$existing) {
                DeviceVendor::create([
                    'device_vendor_uuid' => (string) Str::uuid(),
                    'name'               => $vendor['name'],
                    'enabled'            => $vendor['enabled'],
                    'description'        => $vendor['description'],
                ]);
            }
        }
    }
}
