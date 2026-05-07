<?php

namespace App\Console\Commands\Updates;

use App\Models\DefaultSettings;
use App\Models\Dialplans;
use App\Models\FusionCache;
use Illuminate\Support\Facades\DB;

class Update174
{
    /**
     * Apply update steps.
     *
     * @return bool
     */
    public function apply()
    {
        try {
            DB::transaction(function () {
                $this->addFaxT38DefaultSetting();
                $this->updateFaxDialplans();
            });

            FusionCache::clear('dialplan.*');

            return true;
        } catch (\Throwable $e) {
            echo "Error applying update 174: " . $e->getMessage() . "\n";
            return false;
        }
    }

    private function addFaxT38DefaultSetting(): void
    {
        $attributes = [
            'default_setting_category'    => 'fax',
            'default_setting_subcategory' => 'variable',
            'default_setting_name'        => 'array',
            'default_setting_value'       => 'fax_enable_t38=true',
        ];

        $setting = DefaultSettings::query()->firstOrNew($attributes);

        $setting->default_setting_enabled = true;
        $setting->default_setting_description = 'Enable T.38.';

        if (!$setting->exists) {
            $setting->insert_date = now();
        } else {
            $setting->update_date = now();
        }

        $setting->save();

        echo "Ensured fax default setting fax_enable_t38=true exists.\n";
    }

    private function updateFaxDialplans(): void
    {
        $dialplans = Dialplans::query()
            ->where('dialplan_xml', 'like', '%fax_uuid=%')
            ->where('dialplan_xml', 'like', '%fax_enable_t38_request=true%')
            ->where('dialplan_xml', 'not like', '%fax_enable_t38=true%')
            ->get(['dialplan_uuid', 'dialplan_xml']);

        $updatedCount = 0;

        foreach ($dialplans as $dialplan) {
            $xml = (string) $dialplan->dialplan_xml;
            $updatedXml = $this->insertFaxT38EnableXmlAction($xml);

            if ($updatedXml === $xml) {
                continue;
            }

            $dialplan->dialplan_xml = $updatedXml;
            $dialplan->update_date = now();
            $dialplan->save();

            $updatedCount++;
        }

        echo "Updated {$updatedCount} fax dialplan XML record(s).\n";
    }

    private function insertFaxT38EnableXmlAction(string $xml): string
    {
        $pattern = '/(<action\s+application="set"\s+data="fax_enable_t38_request=true"[^>]*\/>)/';
        $replacement = '<action application="set" data="fax_enable_t38=true"/>' . "\n    " . '$1';

        return preg_replace($pattern, $replacement, $xml, 1) ?? $xml;
    }

}
