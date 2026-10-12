<?php

namespace App\Http\Requests;

use App\Models\PcapSession;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePcapSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return userCheckPermission('pcap_create');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'duration' => ['required', Rule::in(array_keys(PcapSession::DURATIONS))],
            'keep_hours' => ['required', 'integer', Rule::in(PcapSession::KEEP_HOURS)],
            'directions' => ['required', 'array', 'min:1'],
            'directions.*' => [Rule::in(PcapSession::DIRECTIONS)],
            'scope' => ['required', Rule::in([PcapSession::SCOPE_ALL, PcapSession::SCOPE_EXTENSIONS])],
            'extension_uuids' => ['nullable', 'array', 'required_if:scope,' . PcapSession::SCOPE_EXTENSIONS],
            'extension_uuids.*' => [
                'uuid',
                Rule::exists('v_extensions', 'extension_uuid')->where('domain_uuid', session('domain_uuid')),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'directions.required' => __('Choose at least one type of call to capture.'),
            'extension_uuids.required_if' => __('Choose at least one extension.'),
        ];
    }
}
