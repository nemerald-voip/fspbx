<?php

namespace App\Http\Controllers;

use App\Http\Requests\ManageMessageAutoRepliesRequest;
use App\Models\MessageSetting;
use App\Services\Messaging\MessageAutoReplyService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MessageAutoReplyController extends Controller
{
    public function index(ManageMessageAutoRepliesRequest $request, MessageSetting $setting, MessageAutoReplyService $service)
    {
        return response()->json([
            'enabled' => (bool) $setting->auto_replies_enabled,
            'rules' => $service->rules($setting)->orderBy('created_at')->orderBy('message_auto_reply_uuid')->get()
                ->map(fn ($rule) => ['trigger' => $rule->trigger, 'keywords' => json_decode($rule->keywords ?? '[]', true),
                    'reply' => $rule->reply, 'enabled' => (bool) $rule->enabled])->values(),
        ]);
    }

    public function update(ManageMessageAutoRepliesRequest $request, MessageSetting $setting, MessageAutoReplyService $service)
    {
        $data = $request->validated();
        DB::transaction(function () use ($setting, $service, $data) {
            $setting = MessageSetting::where('domain_uuid', $setting->domain_uuid)->whereKey($setting->getKey())->lockForUpdate()->firstOrFail();
            $service->rules($setting)->delete();
            foreach ($data['rules'] as $rule) {
                $service->rules($setting)->insert([
                    'message_auto_reply_uuid' => (string) Str::uuid(), 'domain_uuid' => $setting->domain_uuid,
                    'sms_destination_uuid' => $setting->getKey(), 'trigger' => $rule['trigger'],
                    'keywords' => json_encode($rule['trigger'] === 'keyword'
                        ? array_values(array_unique(array_map(fn ($word) => mb_strtoupper(trim($word), 'UTF-8'), $rule['keywords']))) : []),
                    'reply' => $rule['reply'], 'enabled' => $rule['enabled'], 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            $setting->auto_replies_enabled = $data['enabled'];
            $setting->save();
        });
        return response()->json(['messages' => ['success' => [__('Auto-replies saved.')]]]);
    }
}
