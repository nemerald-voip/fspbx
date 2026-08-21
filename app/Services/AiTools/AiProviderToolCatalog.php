<?php

namespace App\Services\AiTools;

class AiProviderToolCatalog
{
    public const REVISION = 2;
    public const SEND_EMAIL_TOOL_ID = 'fspbx_managed_send_email';
    public const SEND_EMAIL_TOOL_NAME = 'fspbx_send_email';
    public const SEND_EMAIL_RECIPIENT_PLACEHOLDER = 'recipient@example.com';

    public function definitions(string $provider): array
    {
        return match ($provider) {
            'retell' => [$this->retellSendEmailDefinition()],
            default => [],
        };
    }

    public function fingerprint(string $provider): string
    {
        return hash('sha256', json_encode([
            'revision' => self::REVISION,
            'provider' => $provider,
            'tools' => $this->canonicalize($this->definitions($provider)),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    private function retellSendEmailDefinition(): array
    {
        return [
            'type' => 'custom',
            'name' => self::SEND_EMAIL_TOOL_NAME,
            'description' => 'Send an email with the caller details collected by this conversation flow. Use only after confirming the details with the caller. Never tell the caller the email was sent unless this function returns a successful result that confirms it was queued.',
            'tool_id' => self::SEND_EMAIL_TOOL_ID,
            'url' => url('/api/ai-tools/retell/send-email'),
            'method' => 'POST',
            'args_at_root' => false,
            'parameter_type' => 'json',
            'parameters' => [
                'type' => 'object',
                'required' => ['recipient', 'subject', 'fields'],
                'properties' => [
                    'recipient' => [
                        'type' => 'string',
                        'const' => self::SEND_EMAIL_RECIPIENT_PLACEHOLDER,
                        'description' => 'Replace this value with the notification email address while configuring the function in Retell.',
                    ],
                    'subject' => [
                        'type' => 'string',
                        'description' => 'A short subject describing the caller and requested follow-up.',
                    ],
                    'fields' => [
                        'type' => 'array',
                        'description' => 'Only the caller information this flow explicitly collected and confirmed.',
                        'items' => [
                            'type' => 'object',
                            'required' => ['label', 'value'],
                            'properties' => [
                                'label' => [
                                    'type' => 'string',
                                    'description' => 'A short field label such as Name, Callback, Purpose, or Account.',
                                ],
                                'value' => [
                                    'type' => 'string',
                                    'description' => 'The collected and confirmed value for this field.',
                                ],
                            ],
                        ],
                    ],
                    'notes' => [
                        'type' => 'string',
                        'description' => 'Optional additional follow-up information explicitly provided by the caller.',
                    ],
                ],
            ],
            'speak_during_execution' => false,
            'speak_after_execution' => false,
            'timeout_ms' => 10000,
            'max_retry' => 2,
        ];
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
