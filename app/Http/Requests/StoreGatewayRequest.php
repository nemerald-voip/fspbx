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

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            foreach ($this->gatewayAclCidrs() as $cidr) {
                $cidr = trim($cidr);

                if ($cidr === '' || $this->isValidCidr($cidr)) {
                    continue;
                }

                $validator->errors()->add('gateway_acl_cidrs', 'Enter valid provider IP addresses or CIDR ranges.');
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
