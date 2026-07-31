<?php

namespace App\Http\Requests;

use App\Support\Localization\LocaleRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveMenuRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'menu_name' => ['required', 'string', 'max:255'],
            'menu_language' => [
                'required',
                'string',
                Rule::in(array_keys(app(LocaleRegistry::class)->locales())),
            ],
            'menu_description' => ['nullable', 'string', 'max:255'],
        ];
    }
}
