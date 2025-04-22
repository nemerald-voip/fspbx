<?php

namespace App\Http\Requests;

use App\Rules\UniqueExtension;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Http\FormRequest;

class StoreRingGroupRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'ring_group_name'              => ['required', 'string', 'max:75'],

            'ring_group_extension' => [
                'required',
                'numeric',
                new UniqueExtension(),
            ],
            'ring_group_description'   => ['nullable', 'string', 'max:150'],

        ];
    }

    public function messages(): array
    {
        return [
            'ring_group_extension.ring_group_unique' => 'This number is already used',
            'ring_group_extension.RingGroupExists' => 'This number is already used',
        ];
    }


}
