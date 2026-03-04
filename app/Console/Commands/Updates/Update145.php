<?php

namespace App\Console\Commands\Updates;

use Illuminate\Support\Facades\DB;

class Update145
{
    /**
     * Apply update steps.
     *
     * @return bool
     */
    public function apply()
    {
        try {
            DB::beginTransaction();

            $this->renameDefaultSettingsCategory();
            $this->renameDomainSettingsCategory();
            $this->renameScheduledJobSubcategories();

            DB::commit();

            echo "S3 storage settings renamed successfully.\n";
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            echo "Error applying Update141: " . $e->getMessage() . "\n";
            return false;
        }
    }

    /**
     * Rename default settings category from aws to s3 storage.
     *
     * @return void
     */
    protected function renameDefaultSettingsCategory()
    {
        $updated = DB::table('v_default_settings')
            ->where('default_setting_category', 'aws')
            ->update([
                'default_setting_category' => 's3_storage',
            ]);

        echo "Updated {$updated} default setting record(s) from category 'aws' to 's3 storage'.\n";
    }

    /**
     * Rename domain settings category from aws to s3 storage.
     *
     * @return void
     */
    protected function renameDomainSettingsCategory()
    {
        $updated = DB::table('v_domain_settings')
            ->where('domain_setting_category', 'aws')
            ->update([
                'domain_setting_category' => 's3_storage',
            ]);

        echo "Updated {$updated} domain setting record(s) from category 'aws' to 's3 storage'.\n";
    }

    /**
     * Rename scheduled job subcategory prefix from aws_upload_calls_ to s3_upload_calls_.
     *
     * @return void
     */
    protected function renameScheduledJobSubcategories()
    {
        $records = DB::table('v_default_settings')
            ->select('default_setting_uuid', 'default_setting_subcategory')
            ->where('default_setting_category', 'scheduled_jobs')
            ->where('default_setting_subcategory', 'like', 'aws_upload_calls_%')
            ->get();

        $count = 0;

        foreach ($records as $record) {
            $newSubcategory = preg_replace(
                '/^aws_upload_calls_/',
                's3_upload_calls_',
                $record->default_setting_subcategory
            );

            DB::table('v_default_settings')
                ->where('default_setting_uuid', $record->default_setting_uuid)
                ->update([
                    'default_setting_subcategory' => $newSubcategory,
                ]);

            $count++;
        }

        echo "Updated {$count} scheduled job subcategory record(s) from 'aws_upload_calls_' to 's3_upload_calls_'.\n";
    }
}