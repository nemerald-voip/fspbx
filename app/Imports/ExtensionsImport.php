<?php

namespace App\Imports;

use App\Models\Extensions;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Validators\Failure;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsErrors;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class ExtensionsImport implements ToCollection, WithHeadingRow, SkipsEmptyRows, SkipsOnError, SkipsOnFailure, WithValidation
{
    use Importable, SkipsErrors;

    public function rules(): array
    {
        return [
            '*.extension' => [
                'required',
                'numeric',
            ],
        ];
    }

    /**
     * @return array
     */
    public function customValidationMessages()
    {
        return [
            'extension.numeric' => 'Extension must contain digits only',
        ];
    }

    /**
    * @param array $row
    *
    * @return \Illuminate\Database\Eloquent\Model|null
    */
    public function collection(Collection $rows)
    {

        // Validator::make($rows->toArray(), [
        //     '*.extension' => 'required|numeric',
        // ])->validate();
        
        foreach ($rows as $row) 
        {
            $extension = Extensions::create([
                'extension' => $row['extension'],
                'password' => Str::random(30),
                'directory_first_name' => $row['first_name'],
                'directory_last_name' => $row['last_name'],
                'effective_caller_id_name' => $row['first_name'] . ' ' . $row['last_name'],
                'effective_caller_id_number' => $row['extension'],
                'outbound_caller_id_number' => $row['outbound_caller_id_number'],
                'description' => $row['description'],
                'directory_visible' => 'true',
                'directory_exten_visible' => 'true',
                'limit_max' => '5',
                'limit_destination' => '!USER_BUSY',
                'user_context' => Session::get('domain_name'),
                'call_timeout' => 25,
                'call_screen_enabled' => 'false',
                'force_ping' => 'false',
                'enabled' => 'true',

            ]);
        }
        // Log::alert($extension);
        // return $extension;

    }


    /**
     * @param Failure[] $failures
     */
    public function onFailure(Failure ...$failures)
    {
        Log::alert("HERE");
        Log::alert($failures);
        return response()->json([
            'error' => "ERROR",
        ],400);
    }
}
