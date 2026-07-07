<?php

namespace App\Services\Provisioning\Phonebook;

use App\Models\Extensions;
use App\Models\Phonebook;

/**
 * Builds a phonebook's directory: optionally the account's internal extensions,
 * plus the phonebook's own contacts, as a normalized entry list.
 *
 * Each entry: [
 *   'first_name'  => string,
 *   'last_name'   => string,
 *   'name'        => string,   // display name
 *   'numbers'     => string[], // one or more dialable numbers
 *   'group'       => string,   // source phonebook name (directory group)
 *   'group_index' => int,      // 1-based group id (phonebook order)
 * ]
 */
class PhonebookBuilder
{
    /**
     * Build the entries for a single phonebook, scoped to the given domain.
     * Entries are tagged with that phonebook as their directory group.
     *
     * @return array<int, array<string, mixed>>
     */
    public function build(Phonebook $phonebook, string $domainUuid): array
    {
        $entries = $this->tagGroup($this->entriesForPhonebook($phonebook, $domainUuid), $phonebook->name, 1);

        return $this->dedupe($entries);
    }

    /**
     * Build a single merged directory from several phonebooks (used by
     * Grandstream, which downloads one phonebook.xml per device). Each phonebook
     * becomes its own directory group, numbered in the order given.
     *
     * @param  iterable<Phonebook>  $phonebooks
     * @return array<int, array<string, mixed>>
     */
    public function buildMany(iterable $phonebooks, string $domainUuid): array
    {
        $entries = [];
        $index = 1;

        foreach ($phonebooks as $phonebook) {
            $entries = array_merge(
                $entries,
                $this->tagGroup($this->entriesForPhonebook($phonebook, $domainUuid), $phonebook->name, $index)
            );
            $index++;
        }

        return $this->dedupe($entries);
    }

    /**
     * Raw (untagged, un-deduped) entries for one phonebook: extensions when
     * enabled, plus the phonebook's own contacts.
     */
    private function entriesForPhonebook(Phonebook $phonebook, string $domainUuid): array
    {
        $entries = [];

        if ($phonebook->include_extensions) {
            $entries = array_merge($entries, $this->fromExtensions($domainUuid, []));
        }

        return array_merge($entries, $this->fromPhonebookContacts($phonebook));
    }

    /**
     * Tag each entry with its source phonebook as the directory group.
     */
    private function tagGroup(array $entries, string $group, int $index): array
    {
        foreach ($entries as &$entry) {
            $entry['group'] = $group;
            $entry['group_index'] = $index;
        }
        unset($entry);

        return $entries;
    }

    /**
     * The phonebook's own contacts.
     */
    private function fromPhonebookContacts(Phonebook $phonebook): array
    {
        $entries = [];

        foreach ($phonebook->contacts as $contact) {
            $number = trim((string) $contact->phone_number);
            if ($number === '') {
                continue;
            }

            $first = (string) ($contact->first_name ?? '');
            $last  = (string) ($contact->last_name ?? '');
            $name  = trim("$first $last");
            if ($name === '') {
                $name = $number;
            }

            $entries[] = [
                'first_name' => $first,
                'last_name'  => $last,
                'name'       => $name,
                'numbers'    => [$number],
            ];
        }

        return $entries;
    }

    /**
     * Internal extension directory.
     */
    private function fromExtensions(string $domainUuid, array $filters): array
    {
        $query = Extensions::query()
            ->where('domain_uuid', $domainUuid)
            ->where('enabled', 'true');

        // Honor the "directory_visible" flag unless the source explicitly opts out.
        if (($filters['ignore_directory_visible'] ?? false) !== true) {
            $query->where(function ($q) {
                $q->where('directory_visible', 'true')
                    ->orWhereNull('directory_visible');
            });
        }

        $exclude = array_map('strval', (array) ($filters['exclude'] ?? []));

        $entries = [];
        foreach ($query->get(['extension', 'effective_caller_id_name']) as $ext) {
            $number = (string) $ext->extension;
            if ($number === '' || in_array($number, $exclude, true)) {
                continue;
            }

            $displayName = trim((string) ($ext->effective_caller_id_name ?: $number));
            [$first, $last] = $this->splitName($displayName);

            $entries[] = [
                'first_name' => $first,
                'last_name'  => $last,
                'name'       => $displayName,
                'numbers'    => [$number],
            ];
        }

        return $entries;
    }

    /**
     * Split a single display name into first/last parts.
     */
    private function splitName(string $name): array
    {
        $name = trim($name);
        if ($name === '') {
            return ['', ''];
        }

        $parts = preg_split('/\s+/', $name, 2);

        return [$parts[0] ?? '', $parts[1] ?? ''];
    }

    /**
     * Remove duplicate entries (same name + number set) and sort the directory
     * alphabetically by last name, then first name, then display name.
     */
    private function dedupe(array $entries): array
    {
        $seen = [];
        $out = [];

        foreach ($entries as $entry) {
            $key = strtolower($entry['name']) . '|' . implode(',', $entry['numbers']);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $entry;
        }

        usort($out, function ($a, $b) {
            return [strtolower($a['last_name']), strtolower($a['first_name']), strtolower($a['name'])]
                <=> [strtolower($b['last_name']), strtolower($b['first_name']), strtolower($b['name'])];
        });

        return $out;
    }
}
