<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmergencyCallRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Or add your auth logic here
    }

    public function rules(): array
    {
        return [
            'emergency_number'        => 'required|string|max:20',
            'description'             => 'nullable|string|max:255',
            'members'                 => 'nullable|array',
            'members.*.extension_uuid'=> 'required_with:members|uuid',
        ];
        
    }
}
