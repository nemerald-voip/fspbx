<?php

namespace App\Http\Requests;

use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Http\FormRequest;

class StoreZtpOrganizationRequest extends FormRequest
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
        $dhcpOption60TypeList = ['ASCII', 'BINARY'];
        $dhcpBootServerOptionList = ['OPTION66', 'CUSTOM', 'STATIC', 'CUSTOM_OPTION66'];
        $locales = [
            'Chinese_China',
            'Chinese_Taiwan',
            'Danish_Denmark',
            'Dutch_Netherlands',
            'English_Canada',
            'English_United_Kingdom',
            'English_United_States',
            'French_France',
            'German_Germany',
            'Italian_Italy',
            'Japanese_Japan',
            'Korean_Korea',
            'Norwegian_Norway',
            'Polish_Poland',
            'Portuguese_Portugal',
            'Russian_Russia',
            'Slovenian_Slovenia',
            'Spanish_Spain',
            'Swedish_Sweden',
        ];

        return [
            'organization_name' => 'required|string|max:100',
            'software_version' => 'nullable|string|max:100',
            'domain_uuid' => 'required|uuid',
            'dhcp_boot_server_option' => 'nullable|string|in:' . implode(',', $dhcpBootServerOptionList),
            'dhcp_option_60_type' => 'nullable|string|in:' . implode(',', $dhcpOption60TypeList),
            'localization_language' => 'nullable|string|in:' . implode(',', $locales),
            'provisioning_server_address' => 'nullable|string|max:255',
            'provisioning_server_username' => 'nullable|string|max:64',
            'provisioning_server_password' => 'nullable|string|max:64',
            'provisioning_quick_setup' => 'nullable|boolean',
            'provisioning_polling' => 'nullable|boolean',
        ];
    }

    public function prepareForValidation(): void
    {
        if (!$this->has('boot_server_option') || $this->input('boot_server_option') === 'NULL') {
            $this->merge(['boot_server_option' => null]);
        }

        if (!$this->has('option_60_type') || $this->input('option_60_type') === 'NULL') {
            $this->merge(['option_60_type' => null]);
        }

        if (!$this->has('localization_language') || $this->input('localization_language') === 'NULL') {
            $this->merge(['localization_language' => null]);
        }

        $this->mergeBooleanField('provisioning_polling');
        $this->mergeBooleanField('provisioning_quick_setup');
    }

    /**
     * Merge a boolean value into the request based on the presence of a given key.
     *
     * @param string $key
     */
    private function mergeBooleanField(string $key): void
    {
        $this->merge([
            $key => $this->has($key) && (bool) $this->$key,
        ]);
    }

    /**
     * Sanitize the input field to prevent XSS and remove unwanted characters.
     *
     * @param string $input
     * @return string
     */
    protected function sanitizeInput(string $input): string
    {
        // Trim whitespace
        $input = trim($input);

        // Strip HTML tags
        $input = strip_tags($input);

        // Escape special characters
        $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');

        // Remove any non-ASCII characters if necessary (optional)
        $input = preg_replace('/[^\x20-\x7E]/', '', $input);

        return $input;
    }
}
