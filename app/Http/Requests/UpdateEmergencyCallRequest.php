<?php

namespace App\Http\Requests;

use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Http\FormRequest;

class UpdateEmergencyCallRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'emergency_number' => 'required|string|max:20',
            'description' => 'nullable|string|max:255',
            'members' => 'nullable|array',
            'members.*.extension_uuid' => 'required_with:members|uuid',
            'emails' => 'nullable|array',
            'emails.*' => 'email|max:255',
        ];
    }
}
