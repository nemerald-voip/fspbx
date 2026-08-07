<?php

namespace App\Http\Requests;

class ExportExtensionStatisticsRequest extends ExtensionStatisticsRequest
{
    public function authorize(): bool
    {
        return userCheckPermission('xml_cdr_export');
    }
}
