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
            '*.contact_organization' => [
                'required',
                'string',
                Rule::unique('App\Models\Contact', 'contact_organization')
                    ->where('domain_uuid', Session::get('domain_uuid'))
            ],
            '*.phone_number' => [
                'required',
                'numeric',
                Rule::unique('App\Models\ContactPhones', 'phone_number')
                    ->where('domain_uuid', Session::get('domain_uuid'))
            ],
            '*.phone_speed_dial' => [
                'nullable',
                'numeric',
                Rule::unique('App\Models\ContactPhones', 'phone_speed_dial')
                    ->where('domain_uuid', Session::get('domain_uuid'))
            ],
            '*.phone_type_voice' => [
                'nullable',
                'numeric'
            ],
            '*.username' => [
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
            'phone_number.numeric' => 'Phone Number must only contain numeric values',
            'phone_speed_dial.numeric' => 'Speed Dial must only contain numeric values',
            'phone_type_voice.numeric' => 'Phone Type Voice must only contain numeric values',
            'contact_organization.unique' => 'Duplicate Organization Name has been found',
        ];
    }


    public function prepareForValidation($data, $index)
    {
        $data['contact_organization'] = trim($data['contact_organization']);
        $data['phone_number'] = trim($data['phone_number']);
        $data['phone_speed_dial'] = trim($data['phone_speed_dial']);
        $data['phone_type_voice'] = trim($data['phone_type_voice']);
        foreach ($data['username'] as $username) {
            $username = trim($username);
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

            // Create contact
            $contact = Contact::create([
                'contact_organization' => $row['contact_organization'],
                'domain_uuid' => Session::get('domain_uuid'),
                'insert_date' => date('Y-m-d H:i:s'),
                'insert_user' => Session::get('user_uuid'),
            ]);

            // logger($contact);

            //Create contact Phone
            $contact->contactPhone = new ContactPhones();

            $contact->contactPhone->fill([
                'contact_uuid' => $contact->contact_uuid,
                'domain_uuid' => Session::get('domain_uuid'),
                'phone_type_voice' => is_numeric($row['phone_type_voice']) ? $row['phone_type_voice'] : null,
                'phone_number' => $row['phone_number'],
                'phone_speed_dial' => $row['phone_speed_dial'],
                'insert_date' => date('Y-m-d H:i:s'),
                'insert_user' => Session::get('user_uuid'),
            ]);
            $contact->contactPhone->save();


            // logger($contact->contactPhone);

            //Create contact users
            foreach ($row['username'] as $username) {
                $user = User::where('domain_uuid', Session::get('domain_uuid'))
                    ->where('username', $username)
                    ->first();

                if ($user) {
                    $contact->contactUser = new ContactUsers();

                    $contact->contactUser->fill([
                        'contact_uuid' => $contact->contact_uuid,
                        'domain_uuid' => Session::get('domain_uuid'),
                        'user_uuid' => $user->user_uuid,
                        'insert_date' => date('Y-m-d H:i:s'),
                        'insert_user' => Session::get('user_uuid'),
                    ]);
                    $contact->contactUser->save();
                }
            }
        }
        // Log::alert($extension);
        // return $extension;

    }
}
