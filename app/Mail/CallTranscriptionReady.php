<?php

namespace App\Mail;

use Illuminate\Mail\Mailables\Content;

class CallTranscriptionReady extends BaseMailable
{
    public function __construct(public array $data)
    {
        $sentiment = strtolower((string) ($data['sentiment'] ?? 'neutral'));
        $data['sentiment_badge_class'] = match ($sentiment) {
            'positive' => 'badge-positive',
            'negative' => 'badge-negative',
            default => 'badge-neutral',
        };
        $speakerMap = $data['speaker_map'] ?? [];
        $agentLabel = $data['agent_label'] ?? null;
        $data['template_utterances'] = collect($data['utterances'] ?? [])
            ->map(function (array $line) use ($speakerMap, $agentLabel) {
                $speakerLabel = $line['speaker'] ?? '';

                return [
                    'speaker_name' => $speakerMap[$speakerLabel] ?? 'Speaker '.$speakerLabel,
                    'row_class' => $speakerLabel === $agentLabel ? 'is-agent' : 'is-customer',
                    'time' => gmdate('i:s', (int) (($line['start'] ?? 0) / 1000)),
                    'text' => $line['text'] ?? '',
                ];
            })
            ->all();

        $data['email_subject'] = $data['email_subject'] ?? 'Call transcription ready';
        $this->data = $data;

        parent::__construct($data);
        $this->useEmailTemplate('transcription', 'call-ready');
    }

    public function content(): Content
    {
        return $this->databaseTemplateContent(new Content(
            view: 'emails.transcription.call-ready',
            text: 'emails.transcription.call-ready-text',
        ));
    }
}
