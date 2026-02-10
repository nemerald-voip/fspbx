<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class CallTranscriptionReady extends BaseMailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public array $data
    ) {
        parent::__construct($data);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Call transcription ready',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.transcriptions.call-transcription-ready',          // HTML
            text: 'emails.transcriptions.call-transcription-ready-text',     // Plain text
            with: ['data' => $this->data],
        );
    }
}
