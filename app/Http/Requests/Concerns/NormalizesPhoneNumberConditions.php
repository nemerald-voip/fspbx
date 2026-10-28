<?php

namespace App\Http\Requests\Concerns;

use App\Services\PhoneNumberService;
use libphonenumber\PhoneNumberFormat;

trait NormalizesPhoneNumberConditions
{
    protected function normalizePhoneNumberConditions(): void
    {
        $conditions = $this->input('destination_conditions');
        if (! is_array($conditions)) {
            return;
        }

        $numbers = app(PhoneNumberService::class);
        foreach ($conditions as &$condition) {
            if (is_array($condition) && is_string($condition['condition_expression'] ?? null)) {
                $condition['condition_expression'] = $numbers->formatForDomain(
                    $condition['condition_expression'], $this->input('domain_uuid'), PhoneNumberFormat::E164
                );
            }
        }
        unset($condition);
        $this->merge(['destination_conditions' => $conditions]);
    }
}
