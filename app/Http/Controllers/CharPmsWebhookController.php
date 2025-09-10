<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\JsonResponse;
use Laravel\Sanctum\PersonalAccessToken;
use Spatie\WebhookClient\WebhookConfig;
use Spatie\WebhookClient\WebhookProcessor;

// Fast existence checks
use App\Models\Extensions;
use App\Models\HotelRoom;

class CharPmsWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        // ---------------- 1) AUTH: Bearer (Sanctum) ONLY ----------------
        $auth = (string) $request->header('Authorization', '');
        if (!preg_match('/^Bearer\s+(.+)$/i', $auth, $m)) {
            return response()->json(['code' => 401, 'description' => 'Unauthorized (Bearer token required)'], 401);
        }

        $pat = PersonalAccessToken::findToken($m[1]);
        $valid = $pat
            && (!$pat->expires_at || now()->lt($pat->expires_at))
            && ($pat->can('char:webhook') || $pat->abilities === ['*']);

        if (!$valid) {
            return response()->json(['code' => 401, 'description' => 'Unauthorized (invalid/expired token)'], 401);
        }

        // Resolve domain_uuid from the token's user (adjust to your user schema)
        $user = $pat->tokenable; // typically App\Models\User
        $domainUuid =
            (string) ($user->domain_uuid               // preferred: column on users
                ?? optional($user->domain)->uuid       // or relation ->uuid
                ?? optional($user->tenant)->domain_uuid // or tenant->domain_uuid
                ?? '');

        if (!Str::isUuid($domainUuid)) {
            return response()->json(['code' => 7, 'description' => 'Token user is not assigned to a valid domain'], 403);
        }

        // Attach for the job and logs if needed
        $request->merge([
            '_char_token_user_id'   => optional($user)->getKey(),
            '_resolved_domain_uuid' => $domainUuid,
        ]);

        // ---------------- 2) RATE LIMIT (per domain + IP) ----------------
        $ip     = (string) $request->ip();
        $rate   = config('char.rate');
        $key    = "char-pms:{$domainUuid}:{$ip}";
        $max    = (int) ($rate['max_attempts'] ?? 60);
        $decay  = (int) ($rate['decay_seconds'] ?? 60);

        if (RateLimiter::tooManyAttempts($key, $max)) {
            $retryAfter = RateLimiter::availableIn($key);
            // Per spec: 200 + code > 99 => retry later
            return response()->json([
                'code' => 100,
                'description' => "Temporarily throttled. Retry after {$retryAfter} seconds.",
            ], 200);
        }
        RateLimiter::hit($key, $decay);

        // ---------------- 3) BASIC SYNTAX VALIDATION (→ 400) ----------------
        $action = strtoupper((string) $request->input('action', ''));
        if (!in_array($action, ['CHKI', 'UPDATE', 'MOVE', 'CHKO', 'DND', 'WAKE'], true)) {
            return response()->json(['code' => 1, 'description' => 'Unsupported action'], 400);
        }

        // CHAR examples show both 'YYYY/MM/DDTHH:MM:SS' and 'YYYYMMDDHHMMSS'
        $dateOk = function (?string $s): bool {
            if ($s === null || $s === '') return true;
            return (bool) \DateTime::createFromFormat('Y/m/d\TH:i:s', $s)
                || (bool) \DateTime::createFromFormat('YmdHis', $s);
        };

        $ext = $request->input('extension_id');
        $dst = $request->input('destination_id');

        // Per-action syntax checks
        switch ($action) {
            case 'CHKI':
            case 'UPDATE':
            case 'CHKO':
                if (!is_string($ext) || $ext === '') {
                    return response()->json(['code' => 4, 'description' => 'Missing extension_id'], 400);
                }
                foreach (['arrival', 'departure'] as $df) {
                    if (!$dateOk($request->input($df))) {
                        return response()->json(['code' => 6, 'description' => "Invalid {$df} format (use YYYY/MM/DDTHH:MM:SS or YYYYMMDDHHMMSS)"], 400);
                    }
                }
                break;

            case 'MOVE':
                if (!is_string($ext) || $ext === '' || !is_string($dst) || $dst === '') {
                    return response()->json(['code' => 5, 'description' => 'Missing extension_id or destination_id'], 400);
                }
                foreach (['arrival', 'departure'] as $df) {
                    if (!$dateOk($request->input($df))) {
                        return response()->json(['code' => 6, 'description' => "Invalid {$df} format (use YYYY/MM/DDTHH:MM:SS or YYYYMMDDHHMMSS)"], 400);
                    }
                }
                break;

            case 'DND':
                if (!is_string($ext) || $ext === '') {
                    return response()->json(['code' => 4, 'description' => 'Missing extension_id'], 400);
                }
                // active must be boolean
                if (!is_bool($request->input('active'))) {
                    // Allow "true"/"false"/1/0 strings but normalize below before queuing
                    $raw = $request->input('active');
                    $filter = filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                    if (!is_bool($filter)) {
                        return response()->json(['code' => 6, 'description' => 'Invalid active (boolean)'], 400);
                    }
                    // normalize for downstream
                    $request->merge(['active' => (bool) $filter]);
                }
                break;

            case 'WAKE':
                // extension_id required
                if (!is_string($ext) || $ext === '') {
                    return response()->json(['code' => 4, 'description' => 'Missing extension_id'], 400);
                }
                // wake_action required: S (set) or C (cancel)
                $wakeAction = strtoupper((string) $request->input('wake_action', ''));
                if (!in_array($wakeAction, ['S', 'C'], true)) {
                    return response()->json(['code' => 6, 'description' => 'Invalid wake_action (use S or C)'], 400);
                }
                // wake_time required and must match CHAR format
                $wakeTime = (string) $request->input('wake_time', '');
                if (!$dateOk($wakeTime)) {
                    return response()->json(['code' => 6, 'description' => 'Invalid wake_time (use YYYYMMDDTHHMMSS)'], 400);
                }
                // normalize for downstream
                $request->merge([
                    'wake_action' => $wakeAction,
                    'wake_time'   => $wakeTime,
                ]);
                break;
        }

        // ---------------- 4) DATA VALIDATION (→ 403) ----------------
        $findRoomByExt = function (string $domainUuid, string $ext) {
            $extensionUuid = Extensions::query()
                ->where('domain_uuid', $domainUuid)
                ->where('extension', $ext)
                ->value('extension_uuid');
            if (!$extensionUuid) return null;

            return HotelRoom::query()
                ->select(['uuid', 'domain_uuid', 'extension_uuid', 'room_name'])
                ->where('domain_uuid', $domainUuid)
                ->where('extension_uuid', $extensionUuid)
                ->with(['status' => function ($q) {
                    // presence is enough; pick minimal columns
                    $q->select('uuid', 'hotel_room_uuid');
                }])
                ->first();
        };

        switch ($action) {
            case 'CHKI':
            case 'UPDATE':
            case 'CHKO':
                if (!$findRoomByExt($domainUuid, (string) $ext)) {
                    return response()->json(['code' => 8, 'description' => 'Extension does not exist'], 403);
                }
                break;

            case 'MOVE':
                $src   = $findRoomByExt($domainUuid, (string) $ext);
                $dstRm = $findRoomByExt($domainUuid, (string) $dst);

                if (!$src)   return response()->json(['code' => 8,  'description' => 'Source extension does not exist'], 403);
                if (!$dstRm) return response()->json(['code' => 9,  'description' => 'Destination extension does not exist'], 403);

                if (!$src->status) {
                    return response()->json(['code' => 11, 'description' => 'Source room has no active guest'], 403);
                }
                if ($dstRm->status) {
                    return response()->json(['code' => 10, 'description' => 'Destination room is already occupied'], 403);
                }
                break;

            case 'DND': {
                    $room = $findRoomByExt($domainUuid, (string) $ext);
                    if (!$room) {
                        // Only allow DND on extensions that are mapped to a hotel room
                        return response()->json(['code' => 8, 'description' => 'Extension is not mapped to a hotel room'], 403);
                    }

                    // (Optional) stash for the job so it doesn’t need to re-query
                    $request->merge([
                        '_hotel_room_uuid' => $room->uuid,
                        '_extension_uuid'  => $room->extension_uuid,
                    ]);
                    break;
                }

            case 'WAKE': {
                    $room = $findRoomByExt($domainUuid, (string) $ext);
                    if (!$room) {
                        return response()->json(['code' => 8, 'description' => 'Extension is not mapped to a hotel room'], 403);
                    }
                    // Optional: stash for the job so it doesn’t need to re-query
                    $request->merge([
                        '_hotel_room_uuid' => $room->uuid,
                        '_extension_uuid'  => $room->extension_uuid,
                    ]);
                    break;
                }
        }

        // ---------------- 5) Persist + enqueue via Spatie ----------------
        $webhookConfig = new WebhookConfig([
            'name'                 => 'char-pms',
            'signing_secret'       => '', // auth handled here
            'signature_header_name' => 'Authorization',
            'signature_validator'  => \App\Http\Webhooks\SignatureValidators\AlwaysValidSignatureValidator::class,
            'webhook_profile'      => \Spatie\WebhookClient\WebhookProfile\ProcessEverythingWebhookProfile::class,
            'webhook_response'     => \Spatie\WebhookClient\WebhookResponse\DefaultRespondsTo::class,
            'webhook_model'        => \App\Models\WhCall::class,
            'process_webhook_job'  => \App\Http\Webhooks\Jobs\ProcessCharPmsWebhookJob::class,
        ]);

        try {
            (new WebhookProcessor($request, $webhookConfig))->process();
        } catch (\Throwable $e) {
            report($e);
            // Temporary failure: ask CHAR to retry (per spec)
            return response()->json([
                'code' => 100,
                'description' => 'Temporary error while queueing. Please retry shortly.',
            ], 200);
        }

        // Accepted: 200 + code=0
        return response()->json(['code' => 0, 'description' => 'success'], 200);
    }
}
