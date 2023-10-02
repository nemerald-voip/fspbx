<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
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
                'mimes:webm'
            ]
        ];
    }

    public function messages(): array
    {
        return [
            'recorded_file.required' => 'File is required',
            'recorded_file.mimes' => 'Only wav files allowed'
        ];
    }
}
