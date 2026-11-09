<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexOutboundWebhookLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return userCheckPermission('logs_list_view');
    }

    public function rules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'sort' => ['nullable', Rule::in(['created_at', '-created_at'])],
            'filter' => ['nullable', 'array:search,status,domain_uuid,dateRange'],
            'filter.search' => ['nullable', 'string', 'max:255'],
            'filter.status' => ['nullable', Rule::in(['pending', 'retrying', 'delivered', 'failed'])],
            'filter.domain_uuid' => ['nullable', 'string', 'max:36'],
            'filter.dateRange' => ['nullable', 'array', 'size:2'],
            'filter.dateRange.0' => ['required_with:filter.dateRange', 'date'],
            'filter.dateRange.1' => ['required_with:filter.dateRange', 'date', 'after_or_equal:filter.dateRange.0'],
        ];
    }
}
