<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\ContactPhone;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use libphonenumber\PhoneNumberFormat;

class ContactController extends Controller
{
    /** Contacts read per lookup. One extra row is fetched to detect truncation. */
    private const OPTIONS_CONTACT_LIMIT = 50;

    /** Ceiling on returned numbers, since a contact can hold several. */
    private const OPTIONS_NUMBER_LIMIT = 100;

    private ?string $countryCode = null;

    public function options(Request $request): JsonResponse
    {
        $data = $request->validate([
            'channel' => ['required', Rule::in(['fax', 'sms'])],
            'query' => ['nullable', 'string', 'max:100'],
        ]);

        $permission = $data['channel'] === 'fax' ? 'fax_send' : 'messages_view';
        abort_unless(userCheckPermission($permission), 403);

        $domainUuid = $this->currentDomainUuid();
        $search = mb_strtolower(trim((string) ($data['query'] ?? '')));

        $contacts = Contact::query()
            ->with([
                'organization:organization_uuid,name',
                'phones',
            ])
            ->where('domain_uuid', $domainUuid)
            ->whereHas('phones')
            ->when($search !== '', function ($query) use ($search) {
                $like = '%' . $search . '%';
                $digits = preg_replace('/\D+/', '', $search);

                $query->where(function ($query) use ($like, $digits) {
                    $query
                        ->whereRaw('LOWER(COALESCE(first_name, \'\')) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(COALESCE(last_name, \'\')) LIKE ?', [$like])
                        ->orWhereHas('organization', function ($query) use ($like) {
                            $query->whereRaw('LOWER(COALESCE(name, \'\')) LIKE ?', [$like]);
                        })
                        ->orWhereHas('phones', function ($query) use ($like, $digits) {
                            $query->whereRaw('LOWER(phone_number) LIKE ?', [$like]);

                            // Numbers are stored as they were entered, so also match
                            // digit by digit to find "(415) 555-0134" by "4155550134".
                            if (strlen($digits) >= 3) {
                                $query->orWhere(
                                    'phone_number',
                                    'like',
                                    '%' . implode('%', str_split($digits)) . '%'
                                );
                            }
                        });
                });
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            // One past the limit, so we can tell the caller more contacts exist
            // without paying for a second count query.
            ->limit(self::OPTIONS_CONTACT_LIMIT + 1)
            ->get();

        $truncated = $contacts->count() > self::OPTIONS_CONTACT_LIMIT;
        $contacts = $contacts->take(self::OPTIONS_CONTACT_LIMIT);

        $options = $contacts
            ->flatMap(function (Contact $contact) use ($data) {
                $personName = trim($contact->full_name);
                $organization = trim((string) ($contact->organization?->name ?? ''));

                // Company-only contacts are shown under the company name, so repeating
                // it as the subtitle would just duplicate the row.
                $name = $personName !== '' ? $personName : $organization;
                $subtitle = $personName !== '' ? $organization : '';

                return $contact->phones->map(function (ContactPhone $phone) use ($contact, $data, $name, $subtitle) {
                    $number = trim((string) $phone->phone_number);
                    $type = trim((string) $phone->label);
                    $displayName = $name !== '' ? $name : $number;

                    return [
                        'value' => $number,
                        'label' => implode(' · ', array_filter([
                            $displayName,
                            $type !== '' ? ucfirst($type) : null,
                            $number !== $displayName ? $number : null,
                        ])),
                        'contact_uuid' => $contact->contact_uuid,
                        'phone_uuid' => $phone->phone_uuid,
                        'phone_type' => $type,
                        'name' => $displayName,
                        // Display shape comes from the server, per the domain's country.
                        'number_formatted' => $this->toDisplay($number),
                        'organization' => $subtitle !== '' ? $subtitle : null,
                        '_priority' => $this->phoneTypePriority($data['channel'], $type),
                        '_name' => mb_strtolower($displayName),
                    ];
                })->filter(fn (array $option) => $option['value'] !== '');
            })
            ->sortBy([
                ['_priority', 'asc'],
                ['_name', 'asc'],
                ['value', 'asc'],
            ])
            ->unique('value')
            ->map(function (array $option) {
                unset($option['_priority'], $option['_name']);

                return $option;
            })
            ->values();

        // A handful of contacts can still carry more numbers than we want to ship.
        if ($options->count() > self::OPTIONS_NUMBER_LIMIT) {
            $truncated = true;
            $options = $options->take(self::OPTIONS_NUMBER_LIMIT)->values();
        }

        return response()->json([
            'options' => $options,
            'truncated' => $truncated,
            'limit' => self::OPTIONS_CONTACT_LIMIT,
        ]);
    }

    public function show(Request $request, $phoneNumber)
    {
        $domainUuid = $this->currentDomainUuid();

        $phone = $this->contactPhoneForDomain($phoneNumber, $domainUuid);

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
        // Left as requested so existing callers are unaffected; the matched row's
        // own value is reported separately, in the domain's display shape.
        $data['phone_number'] = $phoneNumber;
        $data['phone_number_formatted'] = $this->toDisplay($phone->phone_number);
        $data['phone_type'] = $phone->label;
        $data['name'] = trim($contact->full_name) !== ''
            ? trim($contact->full_name)
            : ($contact->organization?->name ?? $this->toDisplay($phone->phone_number));
        $data['organization'] = $contact->organization?->name;
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
            // A contact needs an identity: a person's name, or the company it belongs to.
            'first_name'   => 'required_without_all:organization_uuid,organization_name|nullable|string',
            'last_name'    => 'nullable|string',
            'title'        => 'nullable|string',
            'department'   => 'nullable|string',
            'email'        => 'nullable|email',
            'website'      => 'nullable|string',
            'organization_uuid' => 'nullable|uuid',
            // A company typed instead of picked - created on the fly for this domain.
            'organization_name' => 'nullable|string|max:255',

            // Granular Address Fields
            'address_street' => 'nullable|string',
            'address_city'   => 'nullable|string',
            'address_state'  => 'nullable|string',
            'address_zip'    => 'nullable|string',

            'notes'          => 'nullable|string',
            'mobile_number'  => 'nullable|string',
            'fax_number'     => 'nullable|string',

            // Label applied to phone_number when the contact is created
            'phone_label'    => ['nullable', Rule::in(['work', 'mobile', 'fax'])],
        ]);

        $domainUuid = $this->currentDomainUuid();

        // Numbers are stored in E.164 for the domain's country, however they were typed.
        $data['phone_number'] = $this->toE164($data['phone_number']);
        $data['mobile_number'] = $this->toE164($data['mobile_number'] ?? null);
        $data['fax_number'] = $this->toE164($data['fax_number'] ?? null);

        DB::beginTransaction();
        try {
            // 1. Organization
            $orgId = $data['organization_uuid'] ?? null;

            if ($orgId && !$this->organizationBelongsToDomain($orgId, $domainUuid)) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'errors' => ['organization_uuid' => [__('Company not found.')]],
                ], 422);
            }

            if (!$orgId && trim((string) ($data['organization_name'] ?? '')) !== '') {
                $orgId = $this->organizationByName($data['organization_name'], $domainUuid)
                    ->organization_uuid;
            }

            // 2. Contact (Find or Create)
            $existingPhone = $this->contactPhoneForDomain($data['phone_number'], $domainUuid);

            $contactFields = [
                // 'contact_uuid' => (string) Str::uuid(),
                'first_name' => $data['first_name'] ?? null,
                'last_name' => $data['last_name'] ?? null,
                'title' => $data['title'] ?? null,
                'organization_uuid' => $orgId,
                'department' => $data['department'] ?? null,
                'notes' => $data['notes'] ?? null,
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
                    'label' => $data['phone_label'] ?? 'work'
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

            $contact->load('organization:organization_uuid,name');

            return response()->json([
                'success' => true,
                'contact' => [
                    'contact_uuid' => $contact->contact_uuid,
                    'name' => trim($contact->full_name) !== ''
                        ? trim($contact->full_name)
                        : ($contact->organization?->name ?? $data['phone_number']),
                    'organization' => $contact->organization?->name,
                    'phone_number' => $data['phone_number'],
                    'phone_number_formatted' => $this->toDisplay($data['phone_number']),
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            logger('ContactController@store error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            // Handle any other exception that may occur
            return response()->json([
                'success' => false,
                'errors' => ['server' => [__('Failed to save contact')]]
            ], 500);
        }
    }

    // ... private methods ...
    private function currentDomainUuid()
    { /* ... */
        return session('domain_uuid');
    }

    private function contactPhoneForDomain(string $phoneNumber, string $domainUuid): ?ContactPhone
    {
        return ContactPhone::query()
            ->whereIn('phone_number', $this->phoneLookupValues($phoneNumber))
            ->where('phoneable_type', (new Contact())->getMorphClass())
            ->whereIn('phoneable_id', Contact::query()
                ->select('contact_uuid')
                ->where('domain_uuid', $domainUuid))
            ->first();
    }

    /**
     * The domain's country, used for every E.164/display conversion here.
     * Memoized: options() formats one number per row and this reads settings.
     */
    private function countryCode(): string
    {
        return $this->countryCode ??= get_domain_setting('country', null) ?? 'US';
    }

    /** Store shape: E.164 for the domain's country, falling back to the input. */
    private function toE164(?string $phoneNumber): ?string
    {
        $phoneNumber = trim((string) $phoneNumber);

        if ($phoneNumber === '') {
            return null;
        }

        return formatPhoneNumber($phoneNumber, $this->countryCode(), PhoneNumberFormat::E164) ?: $phoneNumber;
    }

    /** Display shape: national for the domain's country, falling back to the input. */
    private function toDisplay(?string $phoneNumber): string
    {
        $phoneNumber = trim((string) $phoneNumber);

        if ($phoneNumber === '') {
            return '';
        }

        return formatPhoneNumber($phoneNumber, $this->countryCode(), PhoneNumberFormat::NATIONAL) ?: $phoneNumber;
    }

    /**
     * Numbers saved before normalization exist in whatever shape they were typed,
     * so match on the plausible variants - the same approach as
     * MessageDestinationResolver::buildPhoneLookupValues().
     *
     * @return array<int, string>
     */
    private function phoneLookupValues(string $phoneNumber): array
    {
        $digits = preg_replace('/\D+/', '', $phoneNumber);

        return array_values(array_unique(array_filter([
            $phoneNumber,
            ltrim($phoneNumber, '+'),
            '+' . ltrim($phoneNumber, '+'),
            $digits,
            '+' . $digits,
            $this->toE164($phoneNumber),
        ])));
    }

    private function organizationBelongsToDomain(string $organizationUuid, string $domainUuid): bool
    {
        return Organization::query()
            ->where('organization_uuid', $organizationUuid)
            ->where('domain_uuid', $domainUuid)
            ->exists();
    }

    /**
     * Find a company by name within the domain, or create it. Billing fields are
     * left at their defaults so companies created here stay out of the billing
     * customer list until someone explicitly enables billing on them.
     */
    private function organizationByName(string $name, string $domainUuid): Organization
    {
        $name = trim($name);

        $existing = Organization::query()
            ->where('domain_uuid', $domainUuid)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first();

        return $existing ?? Organization::create([
            'domain_uuid' => $domainUuid,
            'name' => $name,
        ]);
    }

    private function phoneTypePriority(string $channel, string $type): int
    {
        $type = mb_strtolower($type);

        $priority = $channel === 'fax'
            ? ['fax', 'work', 'mobile']
            : ['mobile', 'work', 'fax'];

        $position = array_search($type, $priority, true);

        return $position === false ? count($priority) : $position;
    }


    public function destroy($uuid)
    {
        try {
            $domainUuid = $this->currentDomainUuid();

            // Find the contact, ensuring it belongs to the current domain
            $contact = \App\Models\Contact::where('domain_uuid', $domainUuid)
                ->where('contact_uuid', $uuid)
                ->firstOrFail();

            // Delete the contact. 
            $contact->delete();

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            DB::rollBack();

            logger('ContactController@destroy error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            // Handle any other exception that may occur
            return response()->json([
                'success' => false,
                'errors' => ['server' => [__('Failed to save contact')]]
            ], 500);
        }
    }
}
