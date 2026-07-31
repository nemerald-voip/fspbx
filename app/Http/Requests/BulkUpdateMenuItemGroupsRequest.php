<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkUpdateMenuItemGroupsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'operation' => strtolower((string) $this->input('operation')),
            'group_uuids' => $this->input('group_uuids', []),
        ]);
    }

    public function rules(): array
    {
        return [
            'operation' => ['required', 'string', Rule::in(['add', 'remove', 'replace'])],
            'items' => ['required', 'array', 'min:1'],
            'items.*' => ['required', 'uuid', 'distinct'],
            'group_uuids' => ['present', 'array', 'required_if:operation,add,remove'],
            'group_uuids.*' => ['uuid', 'distinct'],
        ];
    }
}
