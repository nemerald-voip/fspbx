<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\ContactPhone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ContactController extends Controller
{
    public function show(Request $request, $phoneNumber)
    {
        $domainUuid = $this->currentDomainUuid();

        // Find the phone record
        $phone = ContactPhone::where('phone_number', $phoneNumber)->first();

        if (!$phone || !$phone->phoneable) {
            return response()->json(['contact' => null]);
        }

        $contact = $phone->phoneable;

        // Security Check
        if ($contact->domain_uuid !== $domainUuid) {
            return response()->json(['contact' => null]);
        }

        $contact->load(['emails', 'addresses', 'organization', 'phones']);

        $data = $contact->toArray();
        $data['phone_number'] = $phoneNumber;
        $data['email'] = $contact->emails->where('label', 'work')->first()->email_address ?? null;
        $data['website'] = $contact->organization->website ?? null;
        $data['organization_uuid'] = $contact->organization_uuid ?? null;

        // Phone Labels
        $data['mobile_number'] = $contact->phones->where('label', 'mobile')->first()->phone_number ?? null;
        $data['fax_number'] = $contact->phones->where('label', 'fax')->first()->phone_number ?? null;

        // Address Granularity
        $mainAddress = $contact->addresses->first();
        if ($mainAddress) {
            $data['address_street'] = $mainAddress->street;
            $data['address_city']   = $mainAddress->city;
            $data['address_state']  = $mainAddress->region; // 'region' in DB, 'state' in Form
            $data['address_zip']    = $mainAddress->postal_code;
        }

        return response()->json(['contact' => $data]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'phone_number' => 'required|string',
            'first_name'   => 'required|string',
            'last_name'    => 'nullable|string',
            'title'        => 'nullable|string',
            'department'   => 'nullable|string',
            'email'        => 'nullable|email',
            'website'      => 'nullable|string',
            'organization_uuid' => 'nullable|uuid',

            // Granular Address Fields
            'address_street' => 'nullable|string',
            'address_city'   => 'nullable|string',
            'address_state'  => 'nullable|string',
            'address_zip'    => 'nullable|string',

            'notes'          => 'nullable|string',
            'mobile_number'  => 'nullable|string',
            'fax_number'     => 'nullable|string',
        ]);

        $domainUuid = $this->currentDomainUuid();

        DB::beginTransaction();
        try {
            // 1. Organization
            $orgId = $data['organization_uuid'] ?? null;

            // 2. Contact (Find or Create)
            $existingPhone = ContactPhone::where('phone_number', $data['phone_number'])->first();

            $contactFields = [
                // 'contact_uuid' => (string) Str::uuid(),
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'title' => $data['title'], // NEW
                'organization_uuid' => $orgId,
                'department' => $data['department'],
                'notes' => $data['notes'],
            ];

            // 1. Handle Contact Creation/Update
            if ($existingPhone && $existingPhone->phoneable) {
                $contact = $existingPhone->phoneable;
                $contact->update($contactFields);
            } else {
                $contact = Contact::create(array_merge($contactFields, [
                    'domain_uuid' => $domainUuid
                ]));

                $contact->phones()->create([
                    'phone_number' => $data['phone_number'],
                    'label' => 'work'
                ]);
            }

            // 3. Email
            if (!empty($data['email'])) {
                $contact->emails()->updateOrCreate(
                    ['label' => 'work'],
                    ['email_address' => $data['email']]
                );
            }

            // 4. Address (Granular Update)
            if (!empty($data['address_street']) || !empty($data['address_city'])) {
                $contact->addresses()->updateOrCreate(
                    ['label' => 'main'],
                    [
                        'street'      => $data['address_street'],
                        'city'        => $data['address_city'],
                        'region'      => $data['address_state'],
                        'postal_code' => $data['address_zip'],
                        'domain_uuid' => $domainUuid
                    ]
                );
            }

            // 5. Secondary Phones
            if (!empty($data['mobile_number'])) {
                $contact->phones()->updateOrCreate(['label' => 'mobile'], ['phone_number' => $data['mobile_number']]);
            }
            if (!empty($data['fax_number'])) {
                $contact->phones()->updateOrCreate(['label' => 'fax'], ['phone_number' => $data['fax_number']]);
            }

            DB::commit();
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            DB::rollBack();

            logger('ContactController@store error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            // Handle any other exception that may occur
            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Failed to save contact']]
            ], 500);
        }
    }

    // ... private methods ...
    private function currentDomainUuid()
    { /* ... */
        return session('domain_uuid');
    }
}
