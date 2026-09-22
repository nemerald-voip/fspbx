<?php

namespace App\Services\Messaging;

use App\Models\Extensions;
use App\Models\Messages;
use App\Models\SmsDestinations;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use libphonenumber\PhoneNumberFormat;

class MessageParticipantService
{
    /** Include disabled numbers when editing their saved access list. */
    public function assigned($route): Collection
    {
        $ids = DB::table('sms_destination_members')->where('domain_uuid', $route->domain_uuid)
            ->where('sms_destination_uuid', $route->sms_destination_uuid)->pluck('extension_uuid');
        return Extensions::without('advSettings')->where('domain_uuid', $route->domain_uuid)
            ->where(fn ($q) => $q->where('extension', $route->chatplan_detail_data)->orWhereIn('extension_uuid', $ids))
            ->orderBy('extension')->get();
    }

    public function assign($route, array $extensionUuids): void
    {
        $ids = array_values(array_unique($extensionUuids));
        $extensions = Extensions::without('advSettings')->where('domain_uuid', $route->domain_uuid)
            ->whereIn('extension_uuid', $ids)->get();
        if ($extensions->count() !== count($ids)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'allowed_extension_uuids' => [__('Choose extensions from this account.')],
            ]);
        }
        DB::transaction(function () use ($route, $extensions, $ids) {
            $primary = $extensions->firstWhere('extension', $route->chatplan_detail_data)
                ?? $extensions->firstWhere('extension_uuid', $ids[0] ?? null);
            $route->chatplan_detail_data = $primary?->extension;
            $route->save();
            DB::table('sms_destination_members')->where('domain_uuid', $route->domain_uuid)
                ->where('sms_destination_uuid', $route->sms_destination_uuid)->delete();
            foreach ($ids as $id) {
                DB::table('sms_destination_members')->insert([
                    'sms_destination_member_uuid' => (string) \Illuminate\Support\Str::uuid(),
                    'domain_uuid' => $route->domain_uuid, 'sms_destination_uuid' => $route->sms_destination_uuid,
                    'extension_uuid' => $id,
                ]);
            }
        });
    }

    public function routes(string $domainUuid, string $extensionUuid): Collection
    {
        $extension = Extensions::without('advSettings')->where('domain_uuid', $domainUuid)
            ->where('extension_uuid', $extensionUuid)->first();
        if (!$extension) {
            return collect();
        }
        return SmsDestinations::where('domain_uuid', $domainUuid)->where('enabled', 'true')
            ->where(fn ($q) => $q->where('chatplan_detail_data', $extension->extension)
                ->orWhereIn('sms_destination_uuid', DB::table('sms_destination_members')
                    ->select('sms_destination_uuid')->where('domain_uuid', $domainUuid)
                    ->where('extension_uuid', $extensionUuid)))->get();
    }

    public function normalize(string $domainUuid, string $number): string
    {
        return formatPhoneNumber($number, get_domain_setting('country', $domainUuid) ?? 'US', PhoneNumberFormat::E164);
    }

    public function numbers(string $domainUuid, string $extensionUuid): array
    {
        return $this->routes($domainUuid, $extensionUuid)
            ->map(fn ($route) => $this->normalize($domainUuid, $route->destination))->unique()->values()->all();
    }

    public function members(string $domainUuid, string $number): Collection
    {
        $number = $this->normalize($domainUuid, $number);
        $routes = SmsDestinations::where('domain_uuid', $domainUuid)->where('enabled', 'true')->get()
            ->filter(fn ($route) => $this->normalize($domainUuid, $route->destination) === $number);
        $additional = DB::table('sms_destination_members')->where('domain_uuid', $domainUuid)
            ->whereIn('sms_destination_uuid', $routes->pluck('sms_destination_uuid'))->pluck('extension_uuid');
        return Extensions::without('advSettings')->where('domain_uuid', $domainUuid)
            ->where(fn ($q) => $q->whereIn('extension', $routes->pluck('chatplan_detail_data')->filter())
                ->orWhereIn('extension_uuid', $additional))->get();
    }

    public function sender(Messages $message): ?string
    {
        if ($message->direction !== 'out') {
            return null;
        }
        return data_get($message->delivery_meta, 'outbound.meta.sender_name')
            ?: Extensions::without('advSettings')->where('domain_uuid', $message->domain_uuid)
                ->where('extension_uuid', $message->extension_uuid)->first()?->name_formatted;
    }
}
