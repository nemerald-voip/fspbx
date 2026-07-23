<?php

namespace App\Services;

use Illuminate\Support\Collection;

class EmailTemplatePreviewService
{
    public function __construct(private readonly SafeEmailTemplateRenderer $renderer)
    {
    }

    public function render(array $definition): array
    {
        $variables = $this->sampleVariables();

        return [
            'subject' => $this->renderer->renderSubject(
                (string) $definition['template_subject'],
                $variables
            ),
            'html' => $this->renderer->renderHtml(
                (string) $definition['template_html'],
                $variables,
                (string) ($definition['template_layout'] ?? 'standard')
            ),
            'text' => $this->renderer->renderText(
                $definition['template_text'] ?? null,
                $variables
            ),
        ];
    }

    private function sampleVariables(): array
    {
        $notification = (object) [
            'mailbox' => '1001',
            'status' => 'accepted',
            'caller_id_name' => 'Jordan Lee',
            'caller_id_number' => '+1 202-555-0142',
            'message_length_seconds' => 42,
            'message_left_at' => now(),
            'accepted_by_number' => '1002',
            'current_retry' => 1,
            'current_priority' => 1,
            'vm_notify_notification_uuid' => '00000000-0000-4000-8000-000000000001',
            'attempts' => collect([
                (object) [
                    'destination' => '+1 202-555-0188',
                    'status' => 'accepted',
                    'retry_number' => 1,
                    'priority' => 1,
                    'claim_result' => 'Accepted',
                ],
            ]),
        ];

        return [
            'email_subject' => 'Sample email notification',
            'app_name' => config('app.name', 'FS PBX'),
            'product_url' => 'https://example.test',
            'company_name' => 'Example Company',
            'company_address' => '100 Main Street',
            'support_email' => 'support@example.test',
            'domain_uuid' => session('domain_uuid'),

            'name' => 'Jordan Lee',
            'extension' => '1001',
            'domain' => 'example.test',
            'username' => 'jordan@example.test',
            'password' => 'sample-password',
            'password_url' => 'https://example.test/password',
            'qrCodeUrl' => null,
            'google_play_link' => 'https://play.google.com/',
            'apple_store_link' => 'https://www.apple.com/app-store/',
            'windows_link' => 'https://example.test/download/windows',
            'mac_link' => 'https://example.test/download/mac',

            'recipient_name' => 'Jordan Lee',
            'account_name' => 'Example Company',
            'phone_system_address' => 'pbx.example.test',
            'direct_numbers' => ['+1 202-555-0101', '+1 202-555-0102'],
            'voicemail_id' => '1001',
            'voicemail_pin' => '4829',
            'portal_email' => 'jordan@example.test',
            'portal_login_url' => 'https://example.test/login',
            'password_request_url' => 'https://example.test/forgot-password',

            'hostname' => 'pbx.example.test',
            'success' => ['recording-001.wav', 'recording-002.wav'],
            'failed' => [[
                'name' => 'recording-003.wav',
                'msg' => 'Storage unavailable',
            ]],
            'code' => '482913',
            'caller' => '1001',
            'fileUrl' => 'https://example.test/reports/sample.csv',

            'fax_destination' => '+1 202-555-0199',
            'invalid_number' => '555',
            'email_message' => 'The destination did not answer.',
            'from' => 'sender@example.test',
            'caller_display' => 'Jordan Lee (+1 202-555-0142)',
            'fax_pages' => 3,
            'fax_total_pages' => 3,
            'fax_date' => now()->format('Y-m-d H:i'),
            'fax_duration_formatted' => '1 minute 12 seconds',
            'attachment_mime' => 'application/pdf',
            'is_test' => true,
            'help_url' => 'https://example.test/help',
            'pendingFaxes' => 2,
            'waitTimeThreshold' => 15,
            'failedFaxes' => 3,
            'totalChecked' => 20,
            'failureRate' => 15,

            'source' => '+1 202-555-0142',
            'destination' => '+1 202-555-0198',
            'message' => 'This is a sample message.',
            'inline_images' => [],
            'media' => [[
                'original_name' => 'sample.pdf',
                'mime_type' => 'application/pdf',
            ]],
            'caller_id_name' => 'Jordan Lee',
            'caller_id_number' => '+1 202-555-0142',
            'ring_group_display' => 'Customer Service (2000)',
            'destination_number' => '2000',
            'sent_at' => now()->toDateTimeString(),

            'date' => now()->format('F j, Y'),
            'duration' => '00:42',
            'sentiment' => 'Positive',
            'sentiment_badge_class' => 'badge-positive',
            'summary' => 'The caller requested a follow-up about their account.',
            'action_items' => [[
                'owner' => 'Jordan',
                'description' => 'Follow up with the caller tomorrow.',
            ]],
            'template_utterances' => [
                [
                    'speaker_name' => 'Agent',
                    'row_class' => 'is-agent',
                    'time' => '00:03',
                    'text' => 'Thank you for calling. How can I help?',
                ],
                [
                    'speaker_name' => 'Caller',
                    'row_class' => 'is-customer',
                    'time' => '00:08',
                    'text' => 'I have a question about my account.',
                ],
            ],

            'dialed_user' => '1001',
            'message_date' => now()->format('F j, Y g:i A'),
            'message_duration' => '00:42',
            'message_text' => 'Hi, please call me back when you have a moment.',
            'voicemail_file_mode' => 'link',
            'voicemail_download_url' => 'https://example.test/voicemail/sample',
            'notification' => $notification,
            'tenantTimeZone' => 'UTC',
            'subjectLine' => 'Voicemail escalation accepted for mailbox 1001',
            'statusLabel' => 'SUCCESSFUL',
            'template_logs' => new Collection([
                [
                    'time' => now()->format('Y-m-d g:i:s A T'),
                    'level' => 'INFO',
                    'message' => 'The voicemail escalation was accepted.',
                    'destination' => '+1 202-555-0188',
                    'retry_number' => 1,
                    'priority' => 1,
                ],
            ]),
        ];
    }
}
