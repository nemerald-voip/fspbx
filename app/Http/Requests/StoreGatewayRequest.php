<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreGatewayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return userCheckPermission('gateway_add');
    }

    public function rules(): array
    {
        return [
            'gateway' => ['required', 'string', 'max:255'],
            'username' => ['nullable', 'string', 'max:255', 'required_if:register,true'],
            'password' => ['nullable', 'string', 'max:255', 'required_if:register,true'],
            'distinct_to' => ['nullable', 'in:true,false'],
            'auth_username' => ['nullable', 'string', 'max:255'],
            'realm' => ['nullable', 'string', 'max:255'],
            'from_user' => ['nullable', 'string', 'max:255'],
            'from_domain' => ['nullable', 'string', 'max:255'],
            'proxy' => ['required', 'string', 'max:255'],
            'register_proxy' => ['nullable', 'string', 'max:255'],
            'outbound_proxy' => ['nullable', 'string', 'max:255'],
            'expire_seconds' => ['required', 'integer', 'min:1', 'max:65535'],
            'register' => ['required', 'in:true,false'],
            'register_transport' => ['nullable', 'in:udp,tcp,tls'],
            'contact_params' => ['nullable', 'string', 'max:255'],
            'retry_seconds' => ['required', 'integer', 'min:1', 'max:65535'],
            'extension' => ['nullable', 'string', 'max:255'],
            'ping' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'ping_min' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'ping_max' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'contact_in_ping' => ['nullable', 'in:true,false'],
            'channels' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'caller_id_in_from' => ['nullable', 'in:true,false'],
            'supress_cng' => ['nullable', 'in:true,false'],
            'sip_cid_type' => ['nullable', 'in:none,pid,rpid'],
            'codec_prefs' => ['nullable', 'string', 'max:255'],
            'extension_in_contact' => ['nullable', 'in:true,false'],
            'context' => ['required', 'string', 'max:255'],
            'profile' => ['required', 'string', 'max:255'],
            'hostname' => ['nullable', 'string', 'max:255'],
            'enabled' => ['required', 'in:true,false'],
            'description' => ['nullable', 'string', 'max:255'],
            'domain_uuid' => ['nullable', 'uuid'],
            'gateway_acl_cidrs' => ['nullable'],
            'gateway_acl_cidrs.*.node_cidr' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => __('The :attribute field is required.'),
            'string' => __('The :attribute must be a string.'),
            'max' => [
                'string' => __('The :attribute must not be greater than :max characters.'),
                'numeric' => __('The :attribute must not be greater than :max.'),
                'array' => __('The :attribute must not be greater than :max.'),
                'file' => __('The :attribute must not be greater than :max.'),
            ],
            'integer' => __('The :attribute must be an integer.'),
            'min' => __('The :attribute must be at least :min.'),
            'in' => __('The selected :attribute is invalid.'),
            'uuid' => __('The :attribute must be a valid UUID.'),
            'username.required_if' => __('The username is required when registration is enabled.'),
            'password.required_if' => __('The password is required when registration is enabled.'),
        ];
    }

    public function attributes(): array
    {
        return [
            'gateway' => __('Gateway'),
            'username' => __('Username'),
            'password' => __('Password'),
            'distinct_to' => __('Distinct To'),
            'auth_username' => __('Auth Username'),
            'realm' => __('Realm'),
            'from_user' => __('From User'),
            'from_domain' => __('From Domain'),
            'proxy' => __('Proxy'),
            'register_proxy' => __('Register Proxy'),
            'outbound_proxy' => __('Outbound Proxy'),
            'expire_seconds' => __('Expire Seconds'),
            'register' => __('Register'),
            'register_transport' => __('Register Transport'),
            'contact_params' => __('Contact Params'),
            'retry_seconds' => __('Retry Seconds'),
            'extension' => __('Extension'),
            'ping' => __('Ping'),
            'ping_min' => __('Ping Min'),
            'ping_max' => __('Ping Max'),
            'contact_in_ping' => __('Contact In Ping'),
            'channels' => __('Channels'),
            'caller_id_in_from' => __('Caller ID In From'),
            'supress_cng' => __('Suppress CNG'),
            'sip_cid_type' => __('SIP CID Type'),
            'codec_prefs' => __('Codec Preferences'),
            'extension_in_contact' => __('Extension In Contact'),
            'context' => __('Context'),
            'profile' => __('SIP Profile'),
            'hostname' => __('FreeSWITCH Hostname'),
            'enabled' => __('Gateway Enabled'),
            'description' => __('Description'),
            'domain_uuid' => __('Domain'),
            'gateway_acl_cidrs' => __('Provider IPs'),
            'gateway_acl_cidrs.*.node_cidr' => __('IP / CIDR'),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            foreach ($this->gatewayAclCidrs() as $cidr) {
                $cidr = trim($cidr);

                if ($cidr === '' || $this->isValidCidr($cidr)) {
                    continue;
                }

                $validator->errors()->add('gateway_acl_cidrs', __('Enter valid provider IP addresses or CIDR ranges.'));
                break;
            }
        });
    }

    private function gatewayAclCidrs(): array
    {
        $value = $this->input('gateway_acl_cidrs');

        if (is_array($value)) {
            return collect($value)
                ->map(fn ($item) => is_array($item) ? ($item['node_cidr'] ?? null) : $item)
                ->filter(fn ($item) => filled($item))
                ->values()
                ->all();
        }

        return preg_split('/[\r\n,]+/', (string) $value) ?: [];
    }

    private function isValidCidr(string $value): bool
    {
        $parts = explode('/', str_replace('\\', '/', trim($value)), 2);
        $ip = $parts[0] ?? null;

        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }

        if (!isset($parts[1])) {
            return true;
        }

        $max = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? 32 : 128;

        return is_numeric($parts[1]) && $parts[1] >= 0 && $parts[1] <= $max;
    }
}
