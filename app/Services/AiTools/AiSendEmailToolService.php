<?php

namespace App\Services\AiTools;

use App\Jobs\SendAiToolEmail;
use App\Models\AiAgent;
use App\Models\AiToolInvocation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AiSendEmailToolService
{
    public function queue(AiAgent $agent, string $providerCallId, array $args): array
    {
        $recipient = strtolower(trim($args['recipient']));
        $args['recipient'] = $recipient;
        $requestPayload = json_encode(
            $args,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );
        $idempotencyKey = $this->idempotencyKey($agent, $providerCallId, $args);
        $invocationUuid = (string) Str::uuid();
        $now = now();

        $inserted = DB::table('ai_tool_invocations')->insertOrIgnore([
            'ai_tool_invocation_uuid' => $invocationUuid,
            'domain_uuid' => $agent->domain_uuid,
            'ai_agent_uuid' => $agent->getKey(),
            'provider' => $agent->provider,
            'provider_call_id' => $providerCallId,
            'tool_name' => AiProviderToolCatalog::SEND_EMAIL_TOOL_NAME,
            'idempotency_key' => $idempotencyKey,
            'request_payload' => $requestPayload,
            'status' => 'queued',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if ($inserted === 0) {
            $invocation = AiToolInvocation::query()
                ->where('provider', $agent->provider)
                ->where('idempotency_key', $idempotencyKey)
                ->firstOrFail();

            $claimed = AiToolInvocation::query()
                ->whereKey($invocation->getKey())
                ->where('status', 'failed')
                ->update([
                    'status' => 'queued',
                    'last_error' => null,
                    'request_payload' => $requestPayload,
                    'updated_at' => $now,
                ]);

            if ($claimed === 0) {
                return ['queued' => false, 'invocation' => $invocation];
            }

            $invocationUuid = $invocation->getKey();
        }

        SendAiToolEmail::dispatch(
            $invocationUuid,
            $agent->domain_uuid,
            $recipient,
            $args['subject'],
            $args['fields'],
            $args['notes'] ?? null,
        )->afterCommit();

        return [
            'queued' => true,
            'invocation' => AiToolInvocation::query()->findOrFail($invocationUuid),
        ];
    }

    private function idempotencyKey(AiAgent $agent, string $providerCallId, array $args): string
    {
        return hash('sha256', json_encode([
            'agent' => $agent->getKey(),
            'call' => $providerCallId,
            'tool' => AiProviderToolCatalog::SEND_EMAIL_TOOL_NAME,
            'args' => $this->canonicalize($args),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    private function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(fn (mixed $item) => $this->canonicalize($item), $value);
        }

        ksort($value);

        return array_map(fn (mixed $item) => $this->canonicalize($item), $value);
    }
}
