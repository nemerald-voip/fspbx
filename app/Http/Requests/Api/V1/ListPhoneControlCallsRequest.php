<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class ListPhoneControlCallsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'extension' => ['required', 'string', 'max:32'],
        ];
    }

    public function queryParameters(): array
    {
        return [
            'extension' => [
                'description' => 'Extension number whose active calls and call states should be listed.',
                'example' => '1001',
            ],
        ];
    }
}
