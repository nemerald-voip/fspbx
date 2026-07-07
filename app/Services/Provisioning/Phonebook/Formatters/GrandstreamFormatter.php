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

        foreach ($entries as $entry) {
            $contact = $dom->createElement('Contact');

            $contact->appendChild($dom->createElement('FirstName'));
            $contact->lastChild->appendChild($dom->createTextNode((string) ($entry['first_name'] ?? '')));

            $contact->appendChild($dom->createElement('LastName'));
            $contact->lastChild->appendChild($dom->createTextNode((string) ($entry['last_name'] ?? '')));

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

            $root->appendChild($contact);
        }

        return $dom->saveXML();
    }

    public function mime(): string
    {
        return 'application/xml';
    }
}
