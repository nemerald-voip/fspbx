<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Support\Localization\ValidationMessages;
use Illuminate\Validation\Rule;

class StoreOutboundRouteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return userCheckPermission('outbound_route_add');
    }

    public function rules(): array
    {
        return [
            'dialplan_name' => ['required', 'string', 'max:255'],
            'domain_uuid' => ['nullable', 'uuid'],
            'dialplan_context' => ['required', 'string', 'max:255'],
            'gateway' => ['required', 'string', 'max:255'],
            'gateway_2' => ['nullable', 'string', 'max:255'],
            'gateway_3' => ['nullable', 'string', 'max:255'],
            'dialplan_expression' => ['required', 'string', 'max:5000'],
            'prefix_number' => ['nullable', 'string', 'max:32'],
            'limit' => ['nullable', 'string', 'max:32'],
            'accountcode' => ['nullable', 'string', 'max:255'],
            'toll_allow' => ['nullable', 'string', 'max:255'],
            'pin_numbers_enabled' => ['nullable', Rule::in(['true', 'false'])],
            'dialplan_order' => ['required', 'integer', 'min:1', 'max:9999'],
            'dialplan_enabled' => ['required', Rule::in(['true', 'false'])],
            'dialplan_description' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return ValidationMessages::common();
    }

    public function attributes(): array
    {
        return [
            'dialplan_name' => __('Name'),
            'domain_uuid' => __('Domain'),
            'dialplan_context' => __('Context'),
            'gateway' => __('Gateway'),
            'gateway_2' => __('Alternate Gateway 1'),
            'gateway_3' => __('Alternate Gateway 2'),
            'dialplan_expression' => __('Dialplan Expression'),
            'prefix_number' => __('Prefix'),
            'limit' => __('Limit'),
            'accountcode' => __('Account Code'),
            'toll_allow' => __('Toll Allow'),
            'pin_numbers_enabled' => __('PIN Numbers'),
            'dialplan_order' => __('Order'),
            'dialplan_enabled' => __('Enabled'),
            'dialplan_description' => __('Description'),
        ];
    }
}
