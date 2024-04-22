<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use libphonenumber\NumberParseException;
use Propaganistas\LaravelPhone\PhoneNumber;

class UpdatePhoneNumberRequest extends FormRequest
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

        ];
    }

    public function prepareForValidation(): void
    {
        try {
            $destination_caller_id_number = (new PhoneNumber(
                $this->get('destination_number'),
                "US"
            ))->formatE164();
        } catch (NumberParseException $e) {
            $destination_caller_id_number = '';
        }
        $this->merge([
            'destination_caller_id_number' => $destination_caller_id_number
        ]);
    }
}
