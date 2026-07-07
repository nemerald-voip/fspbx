<?php

namespace App\Services;

use App\Models\Phonebook;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class PhonebookService
{
    /**
     * Create or update a phonebook.
     */
    public function save(array $data, ?Phonebook $phonebook = null): Phonebook
    {
        return DB::transaction(function () use ($data, $phonebook) {
            $phonebook ??= new Phonebook();

            if (empty($phonebook->domain_uuid)) {
                $phonebook->domain_uuid = session('domain_uuid');
            }

            $phonebook->fill([
                'name'               => $data['name'],
                'description'        => $data['description'] ?? null,
                'enabled'            => (bool) ($data['enabled'] ?? true),
                'is_default'         => (bool) ($data['is_default'] ?? false),
                'include_extensions' => (bool) ($data['include_extensions'] ?? true),
            ])->save();

            if (array_key_exists('contacts', $data)) {
                $this->syncContacts($phonebook, $data['contacts'] ?? []);
            }

            return $phonebook->fresh('contacts');
        });
    }

    public function duplicate(Phonebook $phonebook, ?string $domainUuid = null): Phonebook
    {
        return DB::transaction(function () use ($phonebook, $domainUuid) {
            $phonebook->loadMissing('contacts');

            $copy = Phonebook::create([
                'domain_uuid'         => $domainUuid ?: session('domain_uuid'),
                'name'                => $this->copyName($phonebook->name),
                'description'         => $phonebook->description,
                'enabled'             => $phonebook->enabled,
                'is_default'          => $phonebook->is_default,
                'include_extensions'  => $phonebook->include_extensions,
            ]);

            $this->syncContacts($copy, $phonebook->contacts->map(fn ($contact) => [
                'first_name'   => $contact->first_name,
                'last_name'    => $contact->last_name,
                'phone_number' => $contact->phone_number,
            ])->all());

            return $copy->fresh('contacts');
        });
    }

    /**
     * Replace a phonebook's contacts with the provided set.
     */
    private function syncContacts(Phonebook $phonebook, array $contacts): void
    {
        $phonebook->contacts()->delete();

        foreach (array_values($contacts) as $i => $contact) {
            $number = trim((string) ($contact['phone_number'] ?? ''));
            if ($number === '') {
                continue;
            }

            $phonebook->contacts()->create([
                'domain_uuid'  => $phonebook->domain_uuid,
                'first_name'   => $contact['first_name'] ?? null,
                'last_name'    => $contact['last_name'] ?? null,
                'phone_number' => $number,
                'sort_order'   => $i,
            ]);
        }
    }

    /**
     * Delete a set of phonebooks. Returns the number removed.
     */
    public function delete(Collection $phonebooks): int
    {
        $deleted = 0;

        foreach ($phonebooks as $phonebook) {
            // Model booted() deleting hook detaches device pivots.
            if ($phonebook->delete()) {
                $deleted++;
            }
        }

        return $deleted;
    }

    private function copyName(?string $name): string
    {
        $base = trim((string) $name);
        $name = $base === '' ? 'Phonebook' : $base;

        return substr($name, 0, 93) . ' (Copy)';
    }
}
