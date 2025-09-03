<?php

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class HotelHousekeepingDefinitionsSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $rows = [
            ['uuid'=>Str::uuid(), 'domain_uuid'=>null, 'code'=>1, 'label'=>'Clean',     'enabled'=>true, 'created_at'=>$now, 'updated_at'=>$now],
            ['uuid'=>Str::uuid(), 'domain_uuid'=>null, 'code'=>2, 'label'=>'Dirty',     'enabled'=>true, 'created_at'=>$now, 'updated_at'=>$now],
            ['uuid'=>Str::uuid(), 'domain_uuid'=>null, 'code'=>3, 'label'=>'Inspected', 'enabled'=>true, 'created_at'=>$now, 'updated_at'=>$now],
            ['uuid'=>Str::uuid(), 'domain_uuid'=>null, 'code'=>3, 'label'=>'Repairing', 'enabled'=>true, 'created_at'=>$now, 'updated_at'=>$now],
            ['uuid'=>Str::uuid(), 'domain_uuid'=>null, 'code'=>3, 'label'=>'Available', 'enabled'=>true, 'created_at'=>$now, 'updated_at'=>$now],
            ['uuid'=>Str::uuid(), 'domain_uuid'=>null, 'code'=>3, 'label'=>'Unavailable', 'enabled'=>true, 'created_at'=>$now, 'updated_at'=>$now],
        ];

        foreach ($rows as $r) {
            DB::table('hotel_housekeeping_definitions')->updateOrInsert(
                ['domain_uuid' => $r['domain_uuid'], 'code' => $r['code']],
                $r
            );
        }
    }
}
