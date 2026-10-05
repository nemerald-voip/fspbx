<?php

namespace App\Http\Requests;

use App\Models\NumberTranslation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveNumberTranslationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return userCheckPermission($this->isMethod('post') ? 'number_translation_add' : 'number_translation_edit');
    }

    public function rules(): array
    {
        $translation = $this->route('number_translation');
        $uuid = $translation instanceof NumberTranslation ? $translation->getKey() : $translation;
        $xmlText = function ($attribute, $value, $fail) {
            if (preg_match('/[\x00-\x1f\x7f]/', $value)) {
                $fail(__('Use a single line without control characters.'));
            }
        };

        return [
            'name' => ['bail', 'required', 'string', 'max:255', 'regex:/^[A-Za-z0-9_.-]+$/D',
                Rule::unique('v_number_translations', 'number_translation_name')->ignore($uuid, 'number_translation_uuid')],
            'description' => ['nullable', 'string', 'max:1000', $xmlText],
            'enabled' => ['required', 'boolean'],
            'rules' => ['present', 'array', 'max:200'],
            'rules.*' => ['array:uuid,regex,replace'],
            'rules.*.uuid' => ['nullable', 'uuid', 'distinct', Rule::exists('v_number_translation_details', 'number_translation_detail_uuid')
                ->where('number_translation_uuid', $uuid ?? '00000000-0000-0000-0000-000000000000')],
            'rules.*.regex' => ['bail', 'required', 'string', 'max:4096', $xmlText, function ($attribute, $value, $fail) {
                // A delimiter forbidden by xmlText leaves the user's PCRE untouched.
                if (@preg_match("\x01" . $value . "\x01", '') === false) {
                    $fail(__('Enter a valid regular expression without surrounding delimiters.'));
                }
            }],
            'rules.*.replace' => ['present', 'nullable', 'string', 'max:4096', $xmlText],
        ];
    }

    public function messages(): array
    {
        return [
            'name.regex' => __('Use letters, numbers, periods, underscores, or hyphens for the profile name.'),
        ];
    }
}
