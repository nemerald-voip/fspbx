<?php

namespace App\Exports;

use App\Models\Contact;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ContactsExport implements FromCollection, WithHeadings
{
    protected $contacts;
    protected $maxUsers;
    protected $searchable = ['contact_organization', 'primaryPhone.phone_number', 'primaryPhone.phone_speed_dial',];

    public function __construct()
    {
        $domainUuid = session('domain_uuid');
        $sortField = request()->get('sortField', 'contact_organization');
        $sortOrder = request()->get('sortOrder', 'asc');
    
        $query = Contact::query()
            ->where('domain_uuid', $domainUuid)
            ->with([
                'primaryPhone' => function ($query) {
                    $query->select('contact_uuid', 'phone_number', 'phone_speed_dial');
                },
                'contact_users.user' => function ($query) {
                    $query->select('user_uuid', 'username');
                }
            ])
            ->orderBy($sortField, $sortOrder);
    
        // Get filter data as an array
        $filterData = request('filterData', []);
        if (!empty($filterData['search'])) {
            $value = $filterData['search'];
            $query->where(function ($query) use ($value) {
                foreach ($this->searchable as $field) {
                    if (strpos($field, '.') !== false) {
                        [$relation, $nestedField] = explode('.', $field, 2);
                        $query->orWhereHas($relation, function ($query) use ($nestedField, $value) {
                            $query->where($nestedField, 'ilike', '%' . $value . '%');
                        });
                    } else {
                        $query->orWhere($field, 'ilike', '%' . $value . '%');
                    }
                }
            });
        }
    
        $this->contacts = $query->get();
    
        // Determine maximum number of assigned users
        $this->maxUsers = $this->contacts->max(function ($contact) {
            return $contact->contact_users ? $contact->contact_users->count() : 0;
        });
    }
    

    public function headings(): array
    {
        // Basic columns first
        $headers = ['contact_name', 'destination_number', 'speed_dial_code'];
        // Add a duplicate header "assigned_user" for each possible user.
        for ($i = 0; $i < $this->maxUsers; $i++) {
            $headers[] = 'assigned_user';
        }
        return $headers;
    }

    public function collection(): Collection
    {
        // Map each contact to a row matching the upload template format.
        $exportData = $this->contacts->map(function ($contact) {
            $destinationNumber = $contact->primaryPhone ? $contact->primaryPhone->phone_number : '';
            $speedDialCode = $contact->primaryPhone ? $contact->primaryPhone->phone_speed_dial : '';
            $row = [
                'contact_name' => $contact->contact_organization,
                'destination_number' => $destinationNumber,
                'speed_dial_code' => $speedDialCode,
            ];

            // Gather all assigned usernames.
            $usernames = [];
            if ($contact->contact_users && $contact->contact_users->isNotEmpty()) {
                foreach ($contact->contact_users as $contactUser) {
                    if ($contactUser->user) {
                        $usernames[] = $contactUser->user->username;
                    }
                }
            }

            // Add as many "assigned_user" fields as the maximum count.
            for ($i = 0; $i < $this->maxUsers; $i++) {
                $row[] = $usernames[$i] ?? '';
            }
            return $row;
        });

        return $exportData;
    }
}
