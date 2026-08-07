<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExtensionStatisticsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return userCheckPermission('xml_cdr_view');
    }

    public function rules(): array
    {
        return [
            'filter' => ['sometimes', 'array'],
            'filter.search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'filter.dateRange' => ['sometimes', 'nullable', 'array', 'size:2'],
            'filter.dateRange.0' => ['required_with:filter.dateRange', 'date'],
            'filter.dateRange.1' => ['required_with:filter.dateRange', 'date', 'after_or_equal:filter.dateRange.0'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', Rule::in(fspbx_pagination_options())],
        ];
    }
}
