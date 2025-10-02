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
            'provider' => 'present',
            'enabled' => 'required|boolean',
            'name' => 'required|string|max:100',
            'software' => 'nullable|string|max:100',
            'bootServerOption' => 'nullable|string|in:' . implode(',', $dhcpBootServerOptionList),
            'option60Type' => 'nullable|string|in:' . implode(',', $dhcpOption60TypeList),
            'localization' => 'nullable|string|in:' . implode(',', $locales),
            'address' => 'nullable|string|max:255',
            'username' => 'nullable|string|max:64',
            'password' => 'nullable|string|max:64',
            'quickSetup' => 'nullable|boolean',
            'polling' => 'nullable|boolean',
            'ucs' => 'nullable|string',
        ];
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

    public function prepareForValidation(): void
    {
        if ($this->has('prov_un')) {
            $this->merge([
                'username' => $this->prov_un,
            ]);
        }

        if ($this->has('prov_pw')) {
            $this->merge([
                'password' => $this->prov_pw,
            ]);
        }
    }
}
