<?php

namespace App\Services\Messaging;

use App\Models\MessageGroup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;
use Ramsey\Uuid\Uuid;

class MessageGroupService
{
    public function recipients(array $numbers, string $local, string $country = 'US'): array
    {
        $phone = PhoneNumberUtil::getInstance();
        $normalized = [];
        foreach ($numbers as $number) {
            try {
                $parsed = $phone->parse((string) $number, $country);
                if (!$phone->isValidNumber($parsed)) throw new \InvalidArgumentException;
                $value = $phone->format($parsed, PhoneNumberFormat::E164);
            } catch (\Throwable $e) {
                throw ValidationException::withMessages(['recipients' => [__('Enter valid recipient phone numbers.')]]);
            }
            if ($value !== $local) $normalized[$value] = $value;
        }
        $normalized = array_values($normalized);
        sort($normalized, SORT_STRING);
        return $normalized;
    }

    public function findOrCreate(string $domainUuid, string $local, array $recipients): MessageGroup
    {
        // A roster is immutable. Order and the replying participant never change its identity.
        $recipients = array_values(array_unique($recipients));
        sort($recipients, SORT_STRING);
        if (count($recipients) < 2) throw new \InvalidArgumentException('A group requires at least two recipients.');
        $uuid = Uuid::uuid5(Uuid::NAMESPACE_URL, 'fspbx:message-group:'.json_encode([$domainUuid, $local, $recipients]))->toString();
        return MessageGroup::firstOrCreate(['message_group_uuid' => $uuid], [
            'domain_uuid' => $domainUuid, 'local_number' => $local, 'recipients' => $recipients,
        ]);
    }

    public function conversation(Builder $query, string $local, string $remote): Builder
    {
        if (Str::isUuid($remote)) {
            return $query->where('message_group_uuid', $remote)->where(fn ($q) =>
                $q->where(fn ($in) => $in->where('direction', 'in')->where('destination', $local))
                    ->orWhere(fn ($out) => $out->where('direction', 'out')->where('source', $local)));
        }
        return $query->whereNull('message_group_uuid')->where(fn ($q) =>
            $q->where(fn ($in) => $in->where('direction', 'in')->where('source', $remote)->where('destination', $local))
                ->orWhere(fn ($out) => $out->where('direction', 'out')->where('source', $local)->where('destination', $remote)));
    }
}
