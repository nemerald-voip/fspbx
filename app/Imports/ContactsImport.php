<?php

namespace App\Imports;

use App\Models\Contact;
use App\Models\ContactPhones;
use App\Models\ContactUsers;
use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Session;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsErrors;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\WithGroupedHeadingRow;

class ContactsImport implements ToCollection, WithHeadingRow, SkipsEmptyRows, WithValidation, WithGroupedHeadingRow
{
    use Importable, SkipsErrors, SkipsFailures;

    public function rules(): array
    {
        return [
            '*.contact_name' => [
                'required',
                'string',
                Rule::unique('App\Models\Contact', 'contact_organization')
                    ->where('domain_uuid', Session::get('domain_uuid'))
            ],
            '*.destination_number' => [
                'required',
                'numeric',
                Rule::unique('App\Models\ContactPhones', 'phone_number')
                    ->where('domain_uuid', Session::get('domain_uuid'))
            ],
            '*.speed_dial_code' => [
                'nullable',
                'numeric',
                Rule::unique('App\Models\ContactPhones', 'phone_speed_dial')
                    ->where('domain_uuid', Session::get('domain_uuid'))
            ],
            // '*.phone_type_voice' => [
            //     'nullable',
            //     'numeric'
            // ],
            '*.assigned_user' => [
                'array',
                'nullable'
            ]
        ];
    }

    /**
     * @return array
     */
    public function customValidationMessages()
    {
        return [
            'contact_name.required' => 'Contact Name field is required',
            'contact_name.string' => 'Contact Name must be a string',
            'phone_number.numeric' => 'Phone Number must only contain numeric values',
            'phone_speed_dial.numeric' => 'Speed Dial must only contain numeric values',
        ];
    }


    public function prepareForValidation($data, $index)
    {
        $fieldsToTrim = ['contact_name', 'destination_number', 'speed_dial_code'];
    
        foreach ($fieldsToTrim as $field) {
            if (isset($data[$field])) {
                $data[$field] = trim($data[$field]);
            }
        }
    
        if (isset($data['assigned_user'])) {
            // If 'username' is a string (single column), convert it to an array
            if (is_string($data['assigned_user'])) {
                $data['assigned_user'] = [$data['assigned_user']];
            }
            foreach ($data['assigned_user'] as &$usernameVal) {
                $usernameVal = trim($usernameVal);
            }
        }
    
        return $data;
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

        foreach ($rows as $row) {

            $domain_uuid = session('domain_uuid');
            $user_uuid = session('user_uuid');

            // Create contact
            $contact = Contact::create([
                'contact_organization' => $row['contact_name'],
                'domain_uuid' => $domain_uuid,
                'insert_date' => now(),
                'insert_user' => $user_uuid,
            ]);

            // logger($contact);

            //Create contact Phone
            $contact->contactPhone = new ContactPhones();

            $contact->contactPhone->fill([
                'contact_uuid' => $contact->contact_uuid,
                'domain_uuid' => $domain_uuid,
                'phone_type_voice' => null,
                'phone_number' => $row['destination_number'],
                'phone_speed_dial' => $row['speed_dial_code'] ?? null,
                'insert_date' => now(),
                'insert_user' => $user_uuid,
            ]);
            $contact->contactPhone->save();


            // logger($contact->contactPhone);

            //Create contact users
            if (isset($row['assigned_user']) && is_array($row['assigned_user'])) {
                foreach ($row['assigned_user'] as $username) {
                    $user = User::where('domain_uuid', $domain_uuid)
                        ->where('username', $username)
                        ->first();
            
                    if ($user) {
                        $contact->contactUser = new ContactUsers();
            
                        $contact->contactUser->fill([
                            'contact_uuid' => $contact->contact_uuid,
                            'domain_uuid' => $domain_uuid,
                            'user_uuid' => $user->user_uuid,
                            'insert_date' => now(),
                            'insert_user' => $user_uuid,
                        ]);
                        $contact->contactUser->save();
                    }
                }
            }
        }
        // Log::alert($extension);
        // return $extension;

    }
}
