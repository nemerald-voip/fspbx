<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Support\Localization\ValidationMessages;
use Illuminate\Support\Facades\Auth;

class StoreRecordingBlobRequest extends FormRequest
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
            'recorded_file' => [
                'required',
                //s'mimes:webm,mp4a'
            ]
        ];
    }

    public function messages(): array
    {
        return [
            ...ValidationMessages::common(),
            'recorded_file.required' => __('File is required'),
            'recorded_file.mimes' => __('Only wav files allowed')
        ];
    }

    public function attributes(): array
    {
        return [
            'recorded_file' => __('File'),
        ];
    }
}
