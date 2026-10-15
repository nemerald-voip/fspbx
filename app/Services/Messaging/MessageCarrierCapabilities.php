<?php

namespace App\Services\Messaging;

use Illuminate\Validation\ValidationException;
use libphonenumber\PhoneNumberType;
use libphonenumber\PhoneNumberUtil;

class MessageCarrierCapabilities
{
    public function supportsGroups(string $carrier): bool
    {
        return in_array($carrier, ['sinch', 'bandwidth'], true);
    }

    public function recipientLimit(string $carrier, string $source): int
    {
        if ($carrier === 'sinch') {
            return 20;
        }

        if ($carrier === 'bandwidth') {
            try {
                $phone = PhoneNumberUtil::getInstance();
                $number = $phone->parse($source, 'US');
                // Bandwidth group MMS is available on local numbers, not toll-free.
                if ($phone->isValidNumber($number) && $number->getCountryCode() === 1
                    && $phone->getNumberType($number) !== PhoneNumberType::TOLL_FREE) {
                    return 10;
                }
            } catch (\Throwable $e) {
                // Invalid or unrecognized sending numbers cannot enable groups.
            }
        }

        return 1;
    }

    public function validateGroup(string $carrier, string $source, array $recipients): void
    {
        $limit = $this->recipientLimit($carrier, $source);
        if ($limit === 1) {
            throw ValidationException::withMessages([
                'recipients' => [__('Group messaging is not available for this sending number.')],
            ]);
        }
        if (count($recipients) > $limit) {
            throw ValidationException::withMessages([
                'recipients' => [__('Choose no more than :count recipients for this sending number.', ['count' => $limit])],
            ]);
        }
    }
}
