<?php

namespace App\Http\Requests;

use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Http\FormRequest;

class TextToSpeechRequest extends FormRequest
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

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'input' => 'required|string|max:1000',
            'model' => 'nullable|string',
            'voice' => 'string',
            'response_format' => 'nullable|string',
            'speed' => 'string',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        
        $this->merge([
            'model' => $this->input('model', 'tts-1-hd'),
            'response_format' => $this->input('response_format', 'wav'),
        ]);

        if ($this->has('voice')) {
            if ($this->voice == 'NULL') {
                $this->merge(['voice' => null]);
            } 
        }

        if ($this->has('speed')) {
            if ($this->speed == 'NULL') {
                $this->merge(['speed' => null]);
            } 
        }
    }

    /**
     * Get custom error messages for validator errors.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'input.required' => 'The message is required.',
            'input.max' => 'Your message can not exceed 1000 characters',
            'input.string' => 'The text input must be a string.',
            'model.string' => 'The model name must be a string.',
            'voice.string' => 'The voice must be selected',
            'response_format.string' => 'The response format must be a string.',
            'speed.string' => 'The speed must be selected.',
        ];
    }
}

