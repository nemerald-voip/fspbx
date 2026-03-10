<?php

namespace App\Imports;

use Illuminate\Validation\Rule;
use App\Models\Destinations;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsErrors;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\ToArray; // Changed from ToCollection
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;

class PhoneNumbersImport implements ToArray, WithHeadingRow, SkipsEmptyRows, SkipsOnError, SkipsOnFailure, WithValidation
{
    use Importable, SkipsErrors, SkipsFailures;

    public function rules(): array
    {
        // We validate the raw CSV data here
        return [
            '*.country_code' => ['nullable', 'numeric'],
            '*.phone_number' => ['required'], 
            // We removed the Unique check here because we will check it 
            // either on the frontend or during the final commit to avoid blocking the preview.
        ];
    }

    public function customValidationMessages()
    {
        return [
            '*.country_code.numeric' => 'Country code must only contain numeric values',
            '*.phone_number.required' => 'Phone number is required.',
        ];
    }

    public function prepareForValidation($data, $index)
    {
        $data['country_code'] = preg_replace('/\D+/', '', $data['country_code']);
        $data['phone_number'] = preg_replace('/\D+/', '', $data['phone_number']);
        return $data;
    }

    /**
     * @param array $array
     * @return array
     */
    public function array(array $array)
    {
        // We do nothing here, the library will return the array to the controller
        return $array;
    }
}