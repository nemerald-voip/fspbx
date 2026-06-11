<?php

namespace App\Imports;

use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsErrors;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class DevicesPreviewImport implements ToArray, WithHeadingRow, SkipsEmptyRows, SkipsOnError, SkipsOnFailure, WithValidation
{
    use Importable, SkipsErrors, SkipsFailures;

    protected string $domainUuid;

    public function __construct()
    {
        $this->domainUuid = session('domain_uuid');
    }

    public function rules(): array
    {
        return [
            '*.mac_address' => ['required', 'mac_address'],
            '*.mac_address_modified' => ['required', 'string', 'size:12'],
            '*.serial_number' => ['nullable', 'string'],
            '*.associated_extension' => [
                'nullable',
                Rule::exists('v_extensions', 'extension')->where('domain_uuid', $this->domainUuid),
            ],
        ];
    }

    public function prepareForValidation($data, $index)
    {
        $mac = strtolower(trim((string) ($data['mac_address'] ?? $data['device_address'] ?? '')));
        $normalizedMac = strtolower(preg_replace('/[^0-9a-f]/i', '', $mac));

        $data['mac_address_modified'] = $normalizedMac;
        $data['mac_address'] = $normalizedMac !== ''
            ? strtolower(implode(':', str_split($normalizedMac, 2)))
            : null;

        $serial = trim((string) ($data['serial_number'] ?? ''));
        $serial = strtolower(preg_replace('/[^a-z0-9]/i', '', $serial));
        $data['serial_number'] = $serial === '' ? null : $serial;

        $data['associated_extension'] = trim((string) ($data['associated_extension'] ?? $data['extension'] ?? ''));
        $data['associated_extension'] = $data['associated_extension'] === '' ? null : $data['associated_extension'];

        return $data;
    }

    public function customValidationAttributes()
    {
        return [
            'mac_address_modified' => 'mac_address',
        ];
    }

    public function customValidationMessages()
    {
        return [
            'associated_extension.exists' => 'The associated extension was not found in this domain.',
        ];
    }

    public function array(array $array)
    {
        return $array;
    }
}
