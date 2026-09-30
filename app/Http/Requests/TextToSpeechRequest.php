<?php

namespace App\Http\Requests;

use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Http\FormRequest;
use App\Support\Localization\ValidationMessages;

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
            'model' => $this->input('model', 'gpt-4o-mini-tts-2025-12-15'),
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
            ...ValidationMessages::common(),
            'input.required' => __('The message is required.'),
            'input.max' => __('Your message can not exceed 1000 characters'),
            'input.string' => __('The text input must be a string.'),
            'model.string' => __('The model name must be a string.'),
            'voice.string' => __('The voice must be selected'),
            'response_format.string' => __('The response format must be a string.'),
            'speed.string' => __('The speed must be selected.'),
        ];
    }

    public function attributes(): array
    {
        return [
            'input' => __('Custom greeting message'),
            'model' => __('Model'),
            'voice' => __('Voice'),
            'response_format' => __('Audio Format'),
            'speed' => __('Speed'),
        ];
    }
}
