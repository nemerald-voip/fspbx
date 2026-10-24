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
        $userUuid = $this->currentUserUuid();
        $search = mb_strtolower(trim((string) ($data['query'] ?? '')));

        $contacts = Contact::query()
            ->with([
                'organization:organization_uuid,name',
                'phones',
            ])
            ->visibleToUser($domainUuid, $userUuid)
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
            ->preferUserOwned($userUuid)
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

                return $contact->phones->map(function (ContactPhone $phone) use ($contact, $data, $name, $subtitle, $organization) {
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
                        'visibility' => $contact->owner_user_uuid === null ? 'shared' : 'private',
                        // The raw fields, so a row can be edited without another request.
                        'first_name' => $contact->first_name,
                        'last_name' => $contact->last_name,
                        'organization_uuid' => $contact->organization_uuid,
                        'organization_name' => $organization !== '' ? $organization : null,
                        'phone_uuid' => $phone->phone_uuid,
                        'phone_type' => $type,
                        'name' => $displayName,
                        // Display shape comes from the server, per the domain's country.
                        'number_formatted' => $this->toDisplay($number),
                        'organization' => $subtitle !== '' ? $subtitle : null,
                        '_ownership_priority' => $contact->owner_user_uuid === null ? 1 : 0,
                        '_priority' => $this->phoneTypePriority($data['channel'], $type),
                        '_name' => mb_strtolower($displayName),
                    ];
                })->filter(fn (array $option) => $option['value'] !== '');
            })
            // A personal name wins when the same number also exists in the shared book.
            ->sortBy([
                ['_ownership_priority', 'asc'],
                ['_priority', 'asc'],
                ['_name', 'asc'],
                ['value', 'asc'],
            ])
            ->unique('value')
            ->sortBy([
                ['_priority', 'asc'],
                ['_name', 'asc'],
                ['value', 'asc'],
            ])
            ->map(function (array $option) {
                unset($option['_priority'], $option['_name'], $option['_ownership_priority']);

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

        $phone = $this->contactPhoneForUser($phoneNumber, $domainUuid, $this->currentUserUuid());

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
            'contact_uuid' => 'nullable|uuid',
            'is_shared' => 'sometimes|boolean',
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
        $userUuid = $this->currentUserUuid();
        $isShared = (bool) ($data['is_shared'] ?? false);

        // Explicit edits preserve ownership. Sharing applies only to new contacts.
        $contact = !empty($data['contact_uuid'])
            ? Contact::visibleToUser($domainUuid, $userUuid)->findOrFail($data['contact_uuid'])
            : null;

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
            // A new shared contact must not overwrite or publish an existing
            // personal contact, even when the phone number matches.
            if (!$contact && !$isShared) {
                $contact = $this->contactPhoneForUser($data['phone_number'], $domainUuid, $userUuid, ownedOnly: true)?->phoneable;
            }

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
            if ($contact) {
                $contact->update($contactFields);
            } else {
                $contact = Contact::create(array_merge($contactFields, [
                    'domain_uuid' => $domainUuid,
                    'owner_user_uuid' => $isShared ? null : $userUuid,
                ]));
            }

            // Keep existing formatted numbers and labels when they already match.
            if (!$contact->phones()->whereIn('phone_number', $this->phoneLookupValues($data['phone_number']))->exists()) {
                $contact->phones()->create([
                    'phone_number' => $data['phone_number'],
                    'label' => $data['phone_label'] ?? 'work',
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
                        'street'      => $data['address_street'] ?? null,
                        'city'        => $data['address_city'] ?? null,
                        'region'      => $data['address_state'] ?? null,
                        'postal_code' => $data['address_zip'] ?? null,
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

            return response()->json([
                'success' => true,
                'contact' => $this->savedContact($contact, $data['phone_number']),
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

    /**
     * Edit one phone book row: the contact's name, company and sharing, and the
     * number on that row. The contact's other details are left as they are.
     */
    public function update(Request $request, $uuid)
    {
        $data = $request->validate([
            'phone_uuid'   => 'required|uuid',
            'is_shared'    => 'sometimes|boolean',
            'phone_number' => 'required|string',
            // A contact needs an identity: a person's name, or the company it belongs to.
            'first_name'   => 'required_without_all:organization_uuid,organization_name|nullable|string',
            'last_name'    => 'nullable|string',
            'organization_uuid' => 'nullable|uuid',
            'organization_name' => 'nullable|string|max:255',
        ]);

        $domainUuid = $this->currentDomainUuid();
        $userUuid = $this->currentUserUuid();

        // Resolve access before the exception handler so an inaccessible UUID is a 404.
        $contact = Contact::visibleToUser($domainUuid, $userUuid)
            ->where('contact_uuid', $uuid)
            ->firstOrFail();
        $phone = $contact->phones()->where('phone_uuid', $data['phone_uuid'])->firstOrFail();

        $orgId = $data['organization_uuid'] ?? null;

        if ($orgId && !$this->organizationBelongsToDomain($orgId, $domainUuid)) {
            return response()->json([
                'success' => false,
                'errors' => ['organization_uuid' => [__('Company not found.')]],
            ], 422);
        }

        $phoneNumber = $this->toE164($data['phone_number']);

        try {
            DB::transaction(function () use ($contact, $phone, $data, $orgId, $domainUuid, $userUuid, $phoneNumber) {
                if (!$orgId && trim((string) ($data['organization_name'] ?? '')) !== '') {
                    $orgId = $this->organizationByName($data['organization_name'], $domainUuid)
                        ->organization_uuid;
                }

                $fields = [
                    'first_name' => $data['first_name'] ?? null,
                    'last_name' => $data['last_name'] ?? null,
                    'organization_uuid' => $orgId,
                ];

                // Un-sharing makes the contact private to whoever saves it, which
                // removes it from everyone else's phone book.
                if (array_key_exists('is_shared', $data)) {
                    $fields['owner_user_uuid'] = $data['is_shared'] ? null : $userUuid;
                }

                $contact->update($fields);

                $phone->update(['phone_number' => $phoneNumber]);
            });

            return response()->json([
                'success' => true,
                'contact' => $this->savedContact($contact, $phoneNumber),
            ]);
        } catch (\Exception $e) {
            logger('ContactController@update error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());

            return response()->json([
                'success' => false,
                'errors' => ['server' => [__('Failed to save contact')]]
            ], 500);
        }
    }

    // ... private methods ...
    private function savedContact(Contact $contact, string $phoneNumber): array
    {
        $contact->load('organization:organization_uuid,name');

        return [
            'contact_uuid' => $contact->contact_uuid,
            'name' => trim($contact->full_name) !== ''
                ? trim($contact->full_name)
                : ($contact->organization?->name ?? $phoneNumber),
            'organization' => $contact->organization?->name,
            'phone_number' => $phoneNumber,
            'phone_number_formatted' => $this->toDisplay($phoneNumber),
        ];
    }

    private function currentDomainUuid()
    { /* ... */
        return session('domain_uuid');
    }

    private function currentUserUuid(): string
    {
        $userUuid = auth()->id();
        abort_unless($userUuid, 401);

        return $userUuid;
    }

    private function contactPhoneForUser(
        string $phoneNumber,
        string $domainUuid,
        string $userUuid,
        bool $ownedOnly = false
    ): ?ContactPhone {
        $numbers = $this->phoneLookupValues($phoneNumber);
        $contacts = $ownedOnly
            ? Contact::ownedByUser($domainUuid, $userUuid)
            : Contact::visibleToUser($domainUuid, $userUuid);

        $contact = $contacts
            ->with(['phones' => fn ($query) => $query->whereIn('phone_number', $numbers)])
            ->whereHas('phones', fn ($query) => $query->whereIn('phone_number', $numbers))
            ->preferUserOwned($userUuid)
            ->orderBy('contact_uuid')
            ->first();

        return $contact?->phones->first()?->setRelation('phoneable', $contact);
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
        // Resolve access before the exception handler so an inaccessible UUID is a 404.
        $contact = Contact::visibleToUser($this->currentDomainUuid(), $this->currentUserUuid())
            ->where('contact_uuid', $uuid)
            ->firstOrFail();

        try {
            DB::transaction(fn () => $contact->delete());

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            logger('ContactController@destroy error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            // Handle any other exception that may occur
            return response()->json([
                'success' => false,
                'errors' => ['server' => [__('Failed to save contact')]]
            ], 500);
        }
    }
}
