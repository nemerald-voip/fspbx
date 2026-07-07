<?php

namespace App\Services\Provisioning\Phonebook\Formatters;

/**
 * Renders directory entries as a Yealink remote phonebook
 * (YealinkIPPhoneDirectory format).
 *
 * @see PhonebookBuilder for the entry shape.
 */
class YealinkFormatter
{
    public function format(array $entries): string
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $root = $dom->createElement('YealinkIPPhoneDirectory');
        $dom->appendChild($root);

        foreach ($entries as $entry) {
            $numbers = array_values(array_filter(
                array_map('strval', $entry['numbers'] ?? []),
                fn ($n) => $n !== ''
            ));

            if (empty($numbers)) {
                continue;
            }

            $dirEntry = $dom->createElement('DirectoryEntry');

            $dirEntry->appendChild($dom->createElement('Name'));
            $dirEntry->lastChild->appendChild($dom->createTextNode((string) ($entry['name'] ?? '')));

            foreach ($numbers as $number) {
                $dirEntry->appendChild($dom->createElement('Telephone'));
                $dirEntry->lastChild->appendChild($dom->createTextNode($number));
            }

            $root->appendChild($dirEntry);
        }

        return $dom->saveXML();
    }

    public function mime(): string
    {
        return 'application/xml';
    }
}
