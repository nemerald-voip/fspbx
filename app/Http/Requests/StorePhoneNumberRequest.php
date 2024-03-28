<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;

class StorePhoneNumberRequest extends FormRequest
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
            'destination_number' => [
                'required',
                'phone:US',
            ],
            'destination_number_modified' => [
                'nullable',
                Rule::unique('App\Models\Destinations', 'destination_number')
                    ->where('domain_uuid', Session::get('domain_uuid'))
            ],
            'destination_caller_id_name' => [
                'nullable',
                'string',
            ],
            'destination_caller_id_number' => [
                'nullable',
                'phone:US',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'destination_number.required' => 'Phone number is required',
            'destination_number.phone' => 'Should be valid US phone number',
            'destination_number_modified.unique' => 'This phone number is already used'
        ];
    }

    public function prepareForValidation(): void
    {
        $macAddress = tokenizeMacAddress($this->get('device_address') ?? '');
        $this->merge([
            'destination_number' => formatMacAddress($macAddress),
            'destination_number_modified' => $macAddress
        ]);
    }
}
