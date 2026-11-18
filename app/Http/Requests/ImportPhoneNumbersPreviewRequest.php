<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportPhoneNumbersPreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return userCheckPermission('destination_import');
    }

    public function rules(): array
    {
        return ['file' => ['required', 'file']];
    }
}
