<?php

namespace App\Mail;

use App\Models\Messages;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;

class SmsToEmail extends BaseMailable
{
    public function __construct(
        public Messages $messageModel,
        public ?string $orgId = null,
        protected array $emailAttachments = [],
    ) {
        parent::__construct([
            'message' => (string) ($messageModel->message ?? ''),
            'source' => $messageModel->source,
            'destination' => $messageModel->destination,
            'type' => $messageModel->type,
            'media' => $messageModel->media ?? [],
            'org_id' => $orgId,
            'created_at' => $messageModel->created_at,
            'inline_images' => $this->buildInlineImages($emailAttachments),
        ]);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.messages.message',
            text: 'emails.messages.message-text'
        );
    }

    public function attachments(): array
    {
        return collect($this->emailAttachments)
            ->map(function (array $attachment) {
                return Attachment::fromData(
                    fn () => $attachment['data'],
                    $attachment['name']
                )->withMime($attachment['mime'] ?? 'application/octet-stream');
            })
            ->all();
    }

    protected function buildInlineImages(array $attachments): array
    {
        return collect($attachments)
            ->filter(function (array $attachment) {
                $mime = strtolower((string) ($attachment['mime'] ?? ''));
                return str_starts_with($mime, 'image/');
            })
            ->values()
            ->all();
    }
}