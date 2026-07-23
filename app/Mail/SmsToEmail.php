<?php

namespace App\Mail;

use App\Models\Messages;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;

class SmsToEmail extends BaseMailable
{
    private array $inlineImages = [];

    public function __construct(
        public Messages $messageModel,
        public ?string $orgId = null,
        protected array $emailAttachments = [],
    ) {
        $messageText = trim((string) ($messageModel->message ?? ''));
        $media = is_array($messageModel->media) ? $messageModel->media : [];
        $this->inlineImages = $this->buildInlineImages($emailAttachments);

        $attributes = [
            'message' => $messageText,
            'source' => $messageModel->source,
            'destination' => $messageModel->destination,
            'type' => $messageModel->type,
            'media' => $media,
            'org_id' => $orgId,
            'created_at' => $messageModel->created_at,
            'inline_images' => $this->inlineImages,
            'email_subject' => 'New message from '.($messageModel->source ?: 'unknown sender'),
        ];

        parent::__construct($attributes);
        $this->useEmailTemplate('messages', 'inbound');

        $inlineImages = $this->inlineImages;
        $this->withSymfonyMessage(function (Email $message) use ($inlineImages) {
            foreach ($inlineImages as $image) {
                $part = (new DataPart($image['data'], $image['name'], $image['mime']))
                    ->asInline()
                    ->setContentId($image['cid']);
                $message->addPart($part);
            }
        });
    }

    public function content(): Content
    {
        return $this->databaseTemplateContent(new Content(
            view: 'emails.messages.inbound',
            text: 'emails.messages.inbound-text',
        ));
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

    private function buildInlineImages(array $attachments): array
    {
        return collect($attachments)
            ->filter(fn (array $attachment) => str_starts_with(
                strtolower((string) ($attachment['mime'] ?? '')),
                'image/'
            ))
            ->values()
            ->map(function (array $attachment, int $index) {
                $name = (string) ($attachment['name'] ?? 'image-'.$index);
                $attachment['name'] = $name;
                $attachment['mime'] = (string) ($attachment['mime'] ?? 'application/octet-stream');
                $attachment['cid'] = 'fspbx-'.sha1($name.'|'.$index).'@inline';

                return $attachment;
            })
            ->all();
    }

}
