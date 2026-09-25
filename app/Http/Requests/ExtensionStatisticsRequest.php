<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Support\Localization\ValidationMessages;
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

    public function messages(): array
    {
        return ValidationMessages::common() + [
            'size.array' => __('The :attribute must contain :size items.'),
        ];
    }

    public function attributes(): array
    {
        return [
            'filter' => __('Filters'),
            'filter.search' => __('Search'),
            'filter.dateRange' => __('Date Range'),
            'filter.dateRange.0' => __('Start Date'),
            'filter.dateRange.1' => __('End Date'),
            'page' => __('Page'),
            'per_page' => __('Items per page'),
        ];
    }
}
