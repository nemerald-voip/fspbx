<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\HotelRoom;
use App\Models\Extensions;
use App\Models\Voicemails;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use App\Models\HotelRoomStatus;
use App\Models\VoicemailMessages;
use App\Models\VoicemailGreetings;
use Illuminate\Support\Facades\DB;
use App\Services\FreeswitchEslService;
use Illuminate\Support\Facades\Storage;

class HotelRoomService
{
    // public function __construct(private FreeswitchEslService $esl) {}

    /**
     * Create a new status row for a guest check-in (no updates).
     *
     * @param  HotelRoom $room
     * @param  array     $payload  (validated request data)
     */
    public function checkIn(HotelRoom $room, array $payload): HotelRoomStatus
    {
        return DB::transaction(function () use ($room, $payload) {
            $localTz = get_local_time_zone($room->domain_uuid);

            // Normalize arrival/departure to UTC Carbon (or null)
            $arrivalUtc   = $this->parseLocalToUtc(Arr::get($payload, 'arrival_date'), $localTz);
            $departureUtc = $this->parseLocalToUtc(Arr::get($payload, 'departure_date'), $localTz);

            // only fields that truly belong to HotelRoomStatus (exclude 'uuid')
            $data = Arr::only($payload, [
                'occupancy_status',
                'housekeeping_status',
                'guest_first_name',
                'guest_last_name',
            ]);

            $room->status()->delete();

            $this->updateExtension($room, $payload);

            // Purge entire mailbox (messages + greetings + recorded name)
            $this->purgeVoicemailBox($room);

            return HotelRoomStatus::create([
                'uuid'            => (string) Str::uuid(),   // new row every time
                'domain_uuid'     => $room->domain_uuid,     // derive (no session reliance)
                'hotel_room_uuid' => $room->uuid,
                ...$data,
                'arrival_date'    => $arrivalUtc,   // stored as UTC
                'departure_date'  => $departureUtc, // stored as UTC
            ]);
        });
    }

    /**
     * Create a new status row for a guest check-out (no updates).
     * Opinionated defaults: mark vacant & set departure_date=now() if not provided elsewhere.
     */
    public function checkOut(HotelRoom $room): bool
    {
        return DB::transaction(function () use ($room) {
            // lock the current status row (if any) to avoid race conditions
            $current = $room->status()->lockForUpdate()->first();

            if (!$current) {
                return false; // idempotent: nothing to delete
            }

            $current->delete();

            $this->vacateExtension($room);

            // Purge entire mailbox first
            $this->purgeVoicemailBox($room);

            return true;
        });
    }

    /**
     * Upsert/merge status fields, converting local datetimes to UTC before save.
     * Use this for CHAR "UPDATE" or front-end partial updates.
     */
    public function update(HotelRoom $room, array $payload): HotelRoomStatus
    {
        return DB::transaction(function () use ($room, $payload) {
            $localTz = get_local_time_zone($room->domain_uuid);

            $arrivalUtc   = $this->parseLocalToUtc(Arr::get($payload, 'arrival_date'), $localTz);
            $departureUtc = $this->parseLocalToUtc(Arr::get($payload, 'departure_date'), $localTz);

            $data = Arr::only($payload, [
                'occupancy_status',
                'housekeeping_status',
                'guest_first_name',
                'guest_last_name',
            ]);

            if (!is_null($arrivalUtc))   $data['arrival_date']   = $arrivalUtc;
            if (!is_null($departureUtc)) $data['departure_date'] = $departureUtc;

            $this->updateExtension($room, $payload);

            $status = $room->status()->first();
            if ($status) {
                $status->fill($data)->save();
                return $status->refresh();
            }

            return HotelRoomStatus::create([
                'uuid'            => (string) Str::uuid(),
                'domain_uuid'     => $room->domain_uuid,
                'hotel_room_uuid' => $room->uuid,
                ...$data,
            ]);
        });
    }

    /**
     * Move the current guest/status from $source to $destination.
     * - Both rooms must belong to the same domain.
     * - Throws \DomainException if source has no status or destination is occupied.
     * - Uses SELECT ... FOR UPDATE to avoid race conditions.
     * - Vacates source extension and sets destination extension to guest name.
     *
     * @return \App\Models\HotelRoomStatus  The moved status (now pointing at destination room)
     * @throws \DomainException
     */
    public function move(HotelRoom $source, HotelRoom $destination): HotelRoomStatus
    {
        if ($source->domain_uuid !== $destination->domain_uuid) {
            throw new \DomainException('Rooms belong to different domains');
        }

        // We'll collect voicemail info inside the txn, then move files after
        $voicemailContext = null;

        $moved = DB::transaction(function () use ($source, $destination, &$voicemailContext) {
            // lock rows & validate
            $srcStatus = HotelRoomStatus::query()
                ->where('domain_uuid', $source->domain_uuid)
                ->where('hotel_room_uuid', $source->uuid)
                ->lockForUpdate()
                ->first();
            if (!$srcStatus) {
                throw new \DomainException('Source room has no active guest/status');
            }

            $dstStatus = HotelRoomStatus::query()
                ->where('domain_uuid', $destination->domain_uuid)
                ->where('hotel_room_uuid', $destination->uuid)
                ->lockForUpdate()
                ->first();
            if ($dstStatus) {
                throw new \DomainException('Destination room is already occupied');
            }

            // Move status record to destination room
            $srcStatus->hotel_room_uuid = $destination->uuid;
            $srcStatus->save();

            // Build destination display name from the current guest
            $guestName = trim(implode(' ', array_filter([
                (string) $srcStatus->guest_first_name,
                (string) $srcStatus->guest_last_name,
            ]))) ?: null;

            // Resolve voicemail info (src & dst) and update DB rows in one go
            $voicemailContext = $this->prepareVoicemailMoveContext($source, $destination);

            if ($voicemailContext) {
                // Repoint all voicemail messages (DB) src -> dst
                \App\Models\VoicemailMessages::query()
                    ->where('domain_uuid', $source->domain_uuid)
                    ->where('voicemail_uuid', $voicemailContext['src']['uuid'])
                    ->update(['voicemail_uuid' => $voicemailContext['dst']['uuid']]);
            
                // Move greetings (DB) + carry over selected greeting
                $this->moveVoicemailGreetings(
                    $source->domain_uuid,
                    $voicemailContext['src']['id'],
                    $voicemailContext['dst']['id']
                );
            }

            // Update extensions (no file ops here)
            $this->vacateExtension($source);                                  // DND=true, VM disabled, name "Vacant"
            $this->updateExtension($destination, ['extension_name' => $guestName, 'extension_id' => $voicemailContext['dst']['id']]); // DND=false, VM enabled, name guest

            return $srcStatus->refresh();
        });

        // After DB commits, move the voicemail files on disk & update MWI (best-effort)
        if ($voicemailContext) {
            $this->moveVoicemailFiles(
                $voicemailContext['domain_name'],
                $voicemailContext['src']['id'],  // voicemail_id (extension number)
                $voicemailContext['dst']['id']   // voicemail_id (extension number)
            );
            $this->sendMwiUpdate($voicemailContext['domain_name'], $voicemailContext['src']['id']);
            $this->sendMwiUpdate($voicemailContext['domain_name'], $voicemailContext['dst']['id']);
        }

        return $moved;
    }





    /**
     * Parse an incoming local-time string/Carbon into UTC Carbon for storage.
     * Accepts formats from CHAR and your front-end that carry no timezone info.
     *
     * @param  string|\DateTimeInterface|null $value  Local time (no TZ) or Carbon
     * @param  string                         $localTz IANA timezone for the hotel/domain
     * @return \Carbon\Carbon|null                     UTC Carbon or null
     */
    private function parseLocalToUtc($value, string $localTz): ?\Carbon\Carbon
    {
        if (empty($value)) {
            return null;
        }

        // If DateTime/Carbon passed, treat as local wall clock and convert to UTC.
        if ($value instanceof \DateTimeInterface) {
            $str = \Carbon\Carbon::instance($value)->format('Y-m-d H:i:s');
            return \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $str, $localTz)->utc();
        }

        $s = trim((string) $value);

        // 1) CHAR canonical: YYYYMMDDHHMMSS (strict and safest)
        if (preg_match('/^\d{14}$/', $s)) {
            $y = (int) substr($s, 0, 4);
            $m = (int) substr($s, 4, 2);
            $d = (int) substr($s, 6, 2);
            $H = (int) substr($s, 8, 2);
            $i = (int) substr($s, 10, 2);
            $s2 = (int) substr($s, 12, 2);

            // basic range validation to avoid corrupted inputs
            if ($y < 1900 || $y > 2100) return null;
            if ($m < 1 || $m > 12)      return null;
            if ($d < 1 || $d > 31)      return null;
            if ($H < 0 || $H > 23)      return null;
            if ($i < 0 || $i > 59)      return null;
            if ($s2 < 0 || $s2 > 59)      return null;

            try {
                return \Carbon\Carbon::create($y, $m, $d, $H, $i, $s2, $localTz)->utc();
            } catch (\Throwable $e) {
                return null;
            }
        }

        // 2) Other exact shapes (only if they match their regex)
        $candidates = [
            // 2015/01/01T10:11:12
            ['pattern' => '/^\d{4}\/\d{2}\/\d{2}T\d{2}:\d{2}:\d{2}$/', 'format' => 'Y/m/d\TH:i:s'],
            // 2015-01-01T10:11:12
            ['pattern' => '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/',   'format' => 'Y-m-d\TH:i:s'],
            // 2015-01-01 10:11:12
            ['pattern' => '/^\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}:\d{2}$/',  'format' => 'Y-m-d H:i:s'],
            // 01/01/2015 10:11:12
            ['pattern' => '/^\d{2}\/\d{2}\/\d{4}\s\d{2}:\d{2}:\d{2}$/', 'format' => 'm/d/Y H:i:s'],
            // 01/01/2015 10:11
            ['pattern' => '/^\d{2}\/\d{2}\/\d{4}\s\d{2}:\d{2}$/',      'format' => 'm/d/Y H:i'],
        ];

        foreach ($candidates as $c) {
            if (preg_match($c['pattern'], $s)) {
                try {
                    $dt = \Carbon\Carbon::createFromFormat($c['format'], $s, $localTz);
                    if ($dt !== false) {
                        return $dt->utc();
                    }
                } catch (\Throwable $e) {
                    // try next
                }
            }
        }

        // No recognized pattern; refuse to guess to avoid corrupt data.
        return null;
    }

    /**
     * Update v_extensions fields for the room's bound extension.
     */
    private function updateExtension(HotelRoom $room, $payload): void
    {
        if (empty($room->extension_uuid)) {
            return; // nothing to update
        }
        $name = trim((string) Arr::get($payload, 'extension_name', ''));

        Extensions::query()
            ->where('domain_uuid', $room->domain_uuid)
            ->where('extension_uuid', $room->extension_uuid)
            ->update([
                'directory_first_name'     => $name !== '' ? $name : null,
                'directory_last_name'      => null,
                'effective_caller_id_name' => $name !== '' ? $name : null,
                'do_not_disturb'           => "false",
            ]);
        logger($payload);

        Voicemails::query()
            ->where('domain_uuid', $room->domain_uuid)
            ->where('voicemail_id', $payload['extension_id'] ?? null)
            ->update([
                'voicemail_enabled'     => "true",
            ]);
    }

    /**
     * Mark extension as Vacant and disable voicemail on check-out.
     */
    private function vacateExtension(HotelRoom $room): void
    {
        if (empty($room->extension_uuid)) return;

        // Find extension number for voicemail_id
        $extensionId = Extensions::query()
            ->where('domain_uuid', $room->domain_uuid)
            ->where('extension_uuid', $room->extension_uuid)
            ->value('extension');

        // Set display name to "Vacant"
        Extensions::query()
            ->where('domain_uuid', $room->domain_uuid)
            ->where('extension_uuid', $room->extension_uuid)
            ->update([
                'directory_first_name'     => 'Vacant',
                'directory_last_name'      => null,
                'effective_caller_id_name' => 'Vacant',
                'do_not_disturb'           => "true",
            ]);

        // Disable voicemail
        if ($extensionId) {
            Voicemails::query()
                ->where('domain_uuid', $room->domain_uuid)
                ->where('voicemail_id', $extensionId)
                ->update(['voicemail_enabled' => "false"]);
        }
    }


    /**
     * Purge an entire voicemail box for the room’s extension:
     * - delete all DB rows for this voicemail_uuid
     * - delete all files under domain_name/<voicemail_id>/ (messages, greetings, recorded_name, etc.)
     * - recreate empty directory
     * - send MWI update
     */
    private function purgeVoicemailBox(HotelRoom $room): void
    {
        if (empty($room->extension_uuid)) return;

        // Resolve numeric extension (voicemail_id)
        $extensionId = Extensions::query()
            ->where('domain_uuid', $room->domain_uuid)
            ->where('extension_uuid', $room->extension_uuid)
            ->value('extension');

        if (!$extensionId) return;

        // Get voicemail row with domain name (for storage path)
        $voicemail = Voicemails::query()
            ->with('domain') // expects ->domain->domain_name relation
            ->where('domain_uuid', $room->domain_uuid)
            ->where('voicemail_id', $extensionId)
            ->first(['voicemail_uuid', 'greeting_id', 'voicemail_id', 'domain_uuid']);

        if (!$voicemail) return;

        // 1) DB: delete all messages tied to this mailbox
        VoicemailMessages::query()
            ->where('voicemail_uuid', $voicemail->voicemail_uuid)
            ->delete();

        VoicemailGreetings::query()
            ->where('voicemail_id', $voicemail->voicemail_id)
            ->where('domain_uuid', $room->domain_uuid)
            ->delete();

        $voicemail->greeting_id = '-1';
        $voicemail->save();

        // 2) Files: delete entire mailbox directory (messages + greetings + recorded name), then recreate
        $domainName = optional($voicemail->domain)->domain_name ?? session('domain_name');
        if ($domainName) {
            $base = $domainName . '/' . $voicemail->voicemail_id;

            try {
                // Remove the whole directory tree
                if (Storage::disk('voicemail')->exists($base)) {
                    Storage::disk('voicemail')->deleteDirectory($base);
                }
                // Recreate empty mailbox directory so future drops don’t fail
                Storage::disk('voicemail')->makeDirectory($base);
            } catch (\Throwable $e) {
                logger('Voicemail purge (files) error: ' . $e->getMessage());
            }

            // 3) Notify phones (clear MWI)
            try {
                $fs  = new FreeswitchEslService();
                $cmd = sprintf("bgapi luarun app.lua voicemail mwi '%s'@'%s'", $voicemail->voicemail_id, $domainName);
                $fs->executeCommand($cmd);
            } catch (\Throwable $e) {
                logger('MWI update error: ' . $e->getMessage());
            }
        }
    }

    /**
     * Prepare voicemail move context: fetch voicemail_uuid/voicemail_id for both source and destination.
     * Returns: [
     *   'domain_name' => 'example.com',
     *   'src' => ['uuid' => ..., 'id' => '101'],
     *   'dst' => ['uuid' => ..., 'id' => '102'],
     * ]
     */
    private function prepareVoicemailMoveContext(HotelRoom $source, HotelRoom $destination): ?array
    {
        // Resolve extension numbers (voicemail_id)
        $srcId = \App\Models\Extensions::query()
            ->where('domain_uuid', $source->domain_uuid)
            ->where('extension_uuid', $source->extension_uuid)
            ->value('extension');

        $dstId = \App\Models\Extensions::query()
            ->where('domain_uuid', $destination->domain_uuid)
            ->where('extension_uuid', $destination->extension_uuid)
            ->value('extension');

        if (!$srcId || !$dstId) return null;

        // Fetch voicemail rows for both
        $srcVm = \App\Models\Voicemails::query()
            ->with('domain')
            ->where('domain_uuid', $source->domain_uuid)
            ->where('voicemail_id', $srcId)
            ->first(['voicemail_uuid', 'voicemail_id', 'domain_uuid']);
        $dstVm = \App\Models\Voicemails::query()
            ->with('domain')
            ->where('domain_uuid', $destination->domain_uuid)
            ->where('voicemail_id', $dstId)
            ->first(['voicemail_uuid', 'voicemail_id', 'domain_uuid']);

        if (!$srcVm || !$dstVm) return null;

        $domainName = optional($srcVm->domain)->domain_name ?? session('domain_name');
        if (!$domainName) return null;

        return [
            'domain_name' => $domainName,
            'src' => ['uuid' => $srcVm->voicemail_uuid, 'id' => $srcVm->voicemail_id],
            'dst' => ['uuid' => $dstVm->voicemail_uuid, 'id' => $dstVm->voicemail_id],
        ];
    }

    /**
     * Move ALL files from one mailbox dir to another (messages, greetings, recorded name, etc.).
     * Example paths:
     *   <domain>/<srcId>/msg_uuid.wav  -> <domain>/<dstId>/msg_uuid.wav
     *   <domain>/<srcId>/greeting_1.wav -> <domain>/<dstId>/greeting_1.wav
     */
    private function moveVoicemailFiles(string $domainName, string $srcId, string $dstId): void
    {
        try {
            $disk   = \Illuminate\Support\Facades\Storage::disk('voicemail');
            $srcDir = $domainName . '/' . $srcId;
            $dstDir = $domainName . '/' . $dstId;

            // Ensure destination dir exists
            if (!$disk->exists($dstDir)) {
                $disk->makeDirectory($dstDir);
            }

            // Move all files (including nested) one by one; create subdirs if needed
            foreach ($disk->allFiles($srcDir) as $srcPath) {
                // Compute path suffix after the srcDir/
                $suffix  = ltrim(substr($srcPath, strlen($srcDir)), '/');
                $dstPath = $dstDir . '/' . $suffix;

                // Ensure subdirectory exists
                $parent = dirname($dstPath);
                if ($parent && !$disk->exists($parent)) {
                    $disk->makeDirectory($parent);
                }

                // Overwrite if exists, then move
                if ($disk->exists($dstPath)) {
                    $disk->delete($dstPath);
                }
                $disk->move($srcPath, $dstPath);
            }

            // Remove any leftover empty directories at source
            if ($disk->exists($srcDir)) {
                $disk->deleteDirectory($srcDir);
            }

            $disk->makeDirectory($srcDir); // recreate empty

        } catch (\Throwable $e) {
            logger('Voicemail file move error: ' . $e->getMessage());
        }
    }

    /** Send MWI update to phones */
    private function sendMwiUpdate(string $domainName, string $voicemailId): void
    {
        try {
            $fs  = new \App\Services\FreeswitchEslService();
            $cmd = sprintf("bgapi luarun app.lua voicemail mwi '%s'@'%s'", $voicemailId, $domainName);
            $fs->executeCommand($cmd);
        } catch (\Throwable $e) {
            logger('MWI update error: ' . $e->getMessage());
        }
    }

    /**
     * Move all greeting rows from SRC voicemail to DST voicemail and
     * carry over the selected greeting. Source greeting_id -> -1.
     */
    private function moveVoicemailGreetings(string $domainUuid, string $srcVoicemailId, string $dstVoicemailId): void
    {
        // Load both mailboxes (we need current greeting selections)
        $srcVm = Voicemails::query()
            ->where('domain_uuid', $domainUuid)
            ->where('voicemail_id', $srcVoicemailId)
            ->first(['voicemail_uuid', 'voicemail_id', 'greeting_id']);

        $dstVm = Voicemails::query()
            ->where('domain_uuid', $domainUuid)
            ->where('voicemail_id', $dstVoicemailId)
            ->first(['voicemail_uuid', 'voicemail_id', 'greeting_id']);

        if (!$srcVm || !$dstVm) {
            return;
        }

        // Move all greeting rows (DB) from SRC -> DST
        VoicemailGreetings::query()
            ->where('domain_uuid', $domainUuid)
            ->where('voicemail_id', $srcVoicemailId)
            ->update(['voicemail_id' => $dstVoicemailId]);

        // Carry over selected greeting if set on source (>= 0)
        $srcGreeting = is_null($srcVm->greeting_id) ? -1 : (int)$srcVm->greeting_id;
        if ($srcGreeting >= 0) {
            $dstVm->greeting_id = $srcGreeting;
            $dstVm->save();
        }

        // Reset source selection to default
        $srcVm->greeting_id = -1;
        $srcVm->save();
    }
}
