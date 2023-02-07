<?php

namespace App\Imports;

use App\Models\Voicemail;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsErrors;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class VoicemailsImport implements ToModel, WithHeadingRow, SkipsEmptyRows, WithValidation, SkipsOnError
{
    use Importable, SkipsErrors;

    public function rules(): array
    {
        return [
            'extension' => [
                'required',
                'numeric',
            ],
        ];
    }

    /**
    * @param array $row
    *
    * @return \Illuminate\Database\Eloquent\Model|null
    */
    public function model(array $row)
    {
        return new Voicemail([
            //
        ]);
    }
}
