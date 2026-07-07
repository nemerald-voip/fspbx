<?php

namespace App\Services\Provisioning\Phonebook\Formatters;

/**
 * Renders directory entries as a Grandstream XML phonebook (AddressBook format).
 *
 * @see PhonebookBuilder for the entry shape.
 */
class GrandstreamFormatter
{
    public function format(array $entries): string
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $root = $dom->createElement('AddressBook');
        $dom->appendChild($root);

        // Each source phonebook becomes a directory group (<pbgroup>), numbered
        // by its group_index so the phone can filter contacts by phonebook.
        $groups = [];
        foreach ($entries as $entry) {
            $index = (int) ($entry['group_index'] ?? 0);
            if ($index > 0 && !isset($groups[$index])) {
                $groups[$index] = (string) ($entry['group'] ?? '');
            }
        }
        ksort($groups, SORT_NUMERIC);

        foreach ($groups as $index => $name) {
            $group = $dom->createElement('pbgroup');
            $group->appendChild($dom->createElement('id'));
            $group->lastChild->appendChild($dom->createTextNode((string) $index));
            $group->appendChild($dom->createElement('name'));
            $group->lastChild->appendChild($dom->createTextNode($name));
            $root->appendChild($group);
        }

        $contactId = 1;
        foreach ($entries as $entry) {
            $contact = $dom->createElement('Contact');

            $contact->appendChild($dom->createElement('id'));
            $contact->lastChild->appendChild($dom->createTextNode((string) $contactId++));

            $contact->appendChild($dom->createElement('FirstName'));
            $contact->lastChild->appendChild($dom->createTextNode((string) ($entry['first_name'] ?? '')));

            $contact->appendChild($dom->createElement('LastName'));
            $contact->lastChild->appendChild($dom->createTextNode((string) ($entry['last_name'] ?? '')));

            $contact->appendChild($dom->createElement('Frequent'));
            $contact->lastChild->appendChild($dom->createTextNode('0'));

            foreach (($entry['numbers'] ?? []) as $number) {
                $number = (string) $number;
                if ($number === '') {
                    continue;
                }

                $phone = $dom->createElement('Phone');
                $phone->setAttribute('type', 'Work');
                $phone->appendChild($dom->createElement('phonenumber'));
                $phone->lastChild->appendChild($dom->createTextNode($number));
                $phone->appendChild($dom->createElement('accountindex'));
                $phone->lastChild->appendChild($dom->createTextNode('0'));

                $contact->appendChild($phone);
            }

            $groupIndex = (int) ($entry['group_index'] ?? 0);
            if ($groupIndex > 0) {
                $contact->appendChild($dom->createElement('Group'));
                $contact->lastChild->appendChild($dom->createTextNode((string) $groupIndex));
            }

            $contact->appendChild($dom->createElement('Primary'));
            $contact->lastChild->appendChild($dom->createTextNode('0'));

            $root->appendChild($contact);
        }

        return $dom->saveXML();
    }

    public function mime(): string
    {
        return 'application/xml';
    }
}
