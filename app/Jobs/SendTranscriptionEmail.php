<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use App\Models\CallTranscription;
use App\Mail\CallTranscriptionReady;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Redis;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;


class SendTranscriptionEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 10;

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     *
     * @var int
     */
    public $maxExceptions = 3;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 120;

    /**
     * Indicate if the job should be marked as failed on timeout.
     *
     * @var bool
     */
    public $failOnTimeout = true;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public $backoff = [30, 60, 120, 300, 1800, 3600];

    /**
     * Delete the job if its models no longer exist.
     *
     * @var bool
     */
    public $deleteWhenMissingModels = true;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(
        public string $transcriptionUuid,
        public string $email
    ) {}

    public function handle()
    {
        Redis::throttle('transcriptions')->allow(1)->every(1)->then(function () {

            $transcription = CallTranscription::find($this->transcriptionUuid);
            if (!$transcription) return;

            // 1. Decode Payloads
            $summaryPayload = $transcription->summary_payload;
            $resultPayload  = $transcription->result_payload;

            // 2. Create Speaker Map (The most important logic)
            // Maps "A" -> "Vanessa (Agent)" and "B" -> "Customer"
            $speakerMap = [];
            $agentLabel = null; // Track who the agent is to highlight them in CSS

            if (isset($summaryPayload['participants'])) {
                foreach ($summaryPayload['participants'] as $p) {
                    $label = $p['label']; // e.g., "A"

                    // Determine display name: Name Guess > Role Guess > Label
                    $name = $p['name_guess'] ?? ucfirst($p['role_guess'] ?? "Speaker $label");
                    $speakerMap[$label] = $name;

                    // Identify if this is the agent (for styling purposes)
                    if (($p['role_guess'] ?? '') === 'agent') {
                        $agentLabel = $label;
                    }
                }
            }

            // 3. Prepare Display Data
            $data = [
                'id'             => $transcription->uuid,
                'date'           => $transcription->created_at->format('F j, Y @ g:i A'),
                'duration'       => gmdate("i:s", $resultPayload['audio_duration'] ?? 0),
                'sentiment'      => ucfirst($summaryPayload['sentiment_overall'] ?? 'Neutral'),
                'summary'        => $summaryPayload['summary'] ?? 'No summary available.',
                'action_items'   => $summaryPayload['action_items'] ?? [],
                'utterances'     => $resultPayload['utterances'] ?? [],
                'speaker_map'    => $speakerMap,
                'agent_label'    => $agentLabel,
                'email_subject'  => 'New transcription'
            ];

            // 4. Send Email
            // Replace with your actual admin notification email
            Mail::to($this->email)->send(new CallTranscriptionReady($data));
        }, function () {
            $this->release(30);
        });
    }
}
