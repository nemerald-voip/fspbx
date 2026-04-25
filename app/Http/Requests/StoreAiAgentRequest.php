<?php

namespace App\Http\Requests;

use App\Rules\UniqueExtension;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Http\FormRequest;

class StoreAiAgentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'agent_name' => 'required|string|max:100',
            'agent_extension' => [
                'required',
                'numeric',
                new UniqueExtension(),
            ],
            'agent_enabled' => 'present',
            'description' => 'nullable|string|max:255',
            'system_prompt' => 'nullable|string|max:5000',
            'first_message' => 'nullable|string|max:500',
            'voice_id' => 'nullable|string',
            'language' => 'nullable|string|max:20',
        ];
    }

    public function attributes(): array
    {
        return [
            'agent_name' => 'name',
            'agent_extension' => 'extension',
        ];
    }
}
