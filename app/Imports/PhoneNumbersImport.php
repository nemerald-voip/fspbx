<?php

namespace App\Imports;

use Illuminate\Support\Str;
use App\Models\Destinations;
use Illuminate\Validation\Rule;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsErrors;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\WithChunkReading;

class PhoneNumbersImport implements ToCollection, WithHeadingRow, SkipsEmptyRows, SkipsOnError, SkipsOnFailure, WithValidation, WithChunkReading
{
    use Importable, SkipsErrors, SkipsFailures;

    private $domain_uuid;

    public function __construct() 
    {
        $this->domain_uuid = session('domain_uuid');
    }

    public function rules(): array
    {
        $table = (new Destinations())->getTable();

        return [
            '*.country_code' => ['nullable', 'numeric'],
            '*.phone_number' => [
                'required',
                Rule::unique($table, 'destination_number'),
            ],
        ];
    }

    /**
     * @return array
     */
    public function customValidationMessages()
    {
        return [
            '*.country_code.numeric' => 'Country code must only contain numeric values',
            '*.phone_number.required' => 'Phone number is required.',
            '*.phone_number.unique'   => 'This phone number already exists.',
        ];
    }


    public function prepareForValidation($data, $index)
    {
        $data['country_code'] = preg_replace('/\D+/', '', $data['country_code']);
        $data['phone_number'] = preg_replace('/\D+/', '', $data['phone_number']);
        return $data;
    }

    /**
     * @param array $row
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {

            //Create extension
            $phone_number = Destinations::create([
                'destination_uuid' => Str::uuid(),
                'domain_uuid' => session('domain_uuid'),
                'dialplan_uuid' => Str::uuid(),
                'destination_prefix' => $row['country_code'],
                'destination_number' => $row['phone_number'],
                'fax_uuid' => null,
                'destination_type' => 'inbound',
                'destination_hold_music' => null,
                'destination_description' => null,
                'destination_enabled' => true,
                'destination_record' => false,
                'destination_type_fax' => false,
                'destination_cid_name_prefix' => null,
                'destination_accountcode' => session('domain_name'),
                'destination_distinctive_ring' => null,
                'destination_context' => 'public',

            ]);

            // Generate dialplan
            dispatch(new \App\Jobs\BuildDialplanForPhoneNumber($phone_number->destination_uuid, session('domain_name')));
        }

    }

    public function chunkSize(): int
    {
        return 1000;
    }
}
