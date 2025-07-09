<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddPrimaryKeyToDeviceCloudProvisioning extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('device_cloud_provisioning')) {
            // Check if the table has a primary key already (optional safeguard)
            $hasPrimaryKey = DB::select("
                SELECT conname 
                FROM pg_constraint 
                WHERE conrelid = 'device_cloud_provisioning'::regclass 
                AND contype = 'p';
            ");

            if (empty($hasPrimaryKey)) {
                // Add primary key on uuid
                DB::statement('ALTER TABLE device_cloud_provisioning ADD PRIMARY KEY (uuid);');
            }

            // Set replica identity to DEFAULT (uses the primary key)
            DB::statement("ALTER TABLE device_cloud_provisioning REPLICA IDENTITY DEFAULT;");
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('device_cloud_provisioning')) {
            // Drop primary key if it exists
            DB::statement('ALTER TABLE device_cloud_provisioning DROP CONSTRAINT IF EXISTS device_cloud_provisioning_pkey;');

            // Optionally set REPLICA IDENTITY to FULL to allow deletes even without a primary key
            DB::statement("ALTER TABLE device_cloud_provisioning REPLICA IDENTITY FULL;");
        }
    }
}
