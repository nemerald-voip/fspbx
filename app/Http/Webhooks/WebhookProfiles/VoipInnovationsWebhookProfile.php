<?php

namespace App\Http\Webhooks\WebhookProfiles;

use Illuminate\Http\Request;
use Spatie\WebhookClient\WebhookProfile\WebhookProfile;

class VoipInnovationsWebhookProfile implements WebhookProfile
{
    public function shouldProcess(Request $request): bool
    {
        $data = $request->json()->all();
        if (!is_string($data['messageType'] ?? null)) {
            return false;
        }
        $type = strtoupper($data['messageType']);
        $uuid = $data['uuid'] ?? null;
        if (!is_string($uuid) || trim($uuid) === '' || strlen($uuid) > 191) {
            return false;
        }

        if (in_array($type, ['SMS', 'MMS'], true)) {
            if (!$this->validNumber($data['from'] ?? null) || !$this->validNumber($data['to'] ?? null)) {
                return false;
            }
            if (isset($data['text']) && (!is_string($data['text']) || strlen($data['text']) > 65535)) {
                return false;
            }
            $files = $data['files'] ?? [];
            if (!is_array($files) || count($files) > 3 || ($type === 'SMS' && $files !== [])) {
                return false;
            }
            foreach ($files as $name => $encoded) {
                if (strlen((string) $name) > 128 || !is_string($encoded) || strlen($encoded) > 2800000) {
                    return false;
                }
                $decoded = base64_decode($encoded, true);
                if ($decoded === false || $decoded === '') {
                    return false;
                }
            }
            return true;
        }

        return in_array($type, ['DELIVERY_RECEIPT', 'STATUS_UPDATE', 'DELIVERED', 'FAILED', 'DLR'], true)
            && (!isset($data['status']) || (is_string($data['status']) && strlen($data['status']) <= 80));
    }

    private function validNumber(mixed $number): bool
    {
        return is_string($number) && preg_match('/^\+?[1-9][0-9]{9,14}$/', trim($number)) === 1;
    }
}
