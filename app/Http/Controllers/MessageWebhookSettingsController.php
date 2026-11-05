<?php

namespace App\Http\Controllers;

use App\Rules\PublicWebhookUrl;
use App\Services\Messaging\MessageSettingsAccess;
use App\Services\Messaging\MessageWebhookSettings;
use Illuminate\Http\Request;

class MessageWebhookSettingsController extends Controller
{
    public function update(Request $request, MessageWebhookSettings $settings)
    {
        abort_unless(userCheckPermission('account_settings_list_view') && MessageSettingsAccess::canManage(), 403);
        $data = $request->validate([
            'endpoint_url' => ['present', 'nullable', 'string', 'max:2048', new PublicWebhookUrl()],
        ]);
        $domainUuid = (string) session('domain_uuid');
        $settings->save($domainUuid, $data['endpoint_url']);

        return response()->json([
            ...$settings->props($domainUuid, true),
            'messages' => ['success' => [__('Messaging webhook settings saved.')]],
        ]);
    }

    /** Returns the account signing secret, creating it on first use, for messaging managers only. */
    public function secret(Request $request, MessageWebhookSettings $settings)
    {
        $data = $request->validate(['domain_uuid' => ['nullable', 'uuid']]);
        $domainUuid = $data['domain_uuid'] ?? (string) session('domain_uuid');
        abort_unless(MessageSettingsAccess::canManage($domainUuid), 403);

        return response()->json(['signing_secret' => $settings->ensureSecret($domainUuid)->signing_secret]);
    }
}
