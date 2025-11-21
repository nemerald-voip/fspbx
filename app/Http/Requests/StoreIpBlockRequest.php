<?php

namespace App\Http\Requests;

use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Http\FormRequest;

class StoreIpBlockRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'ip_address' => [
                'required',
                'string',
                // Custom rule to check for valid IP OR valid CIDR
                function ($attribute, $value, $fail) {
                    // Check if it is a simple valid IP
                    if (filter_var($value, FILTER_VALIDATE_IP)) {
                        return;
                    }

                    // Check if it is a valid CIDR (e.g. 192.168.1.0/24)
                    $parts = explode('/', $value);
                    if (count($parts) === 2) {
                        $ip = $parts[0];
                        $netmask = $parts[1];

                        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && $netmask >= 0 && $netmask <= 32) {
                            return;
                        }
                        // Optional: Add IPv6 CIDR logic here if needed
                    }

                    $fail('The ' . $attribute . ' must be a valid IP address or CIDR subnet.');
                },
            ],
        ];
    }
}
