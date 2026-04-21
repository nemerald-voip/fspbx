<?php

namespace App\Data\Api\V1;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

class CdrData extends Data
{
    public function __construct(
        public string $xml_cdr_uuid,
        public string $object,
        public string $domain_uuid,

        public string|Optional|null $sip_call_id = new Optional(),
        public string|Optional|null $extension_uuid = new Optional(),
        public string|Optional|null $call_center_queue_uuid = new Optional(),

        public string|Optional|null $direction = new Optional(),

        public string|Optional|null $caller_id_name = new Optional(),
        public string|Optional|null $caller_id_number = new Optional(),
        public string|Optional|null $caller_destination = new Optional(),
        public string|Optional|null $destination_number = new Optional(),

        public int|Optional|null $start_epoch = new Optional(),
        public int|Optional|null $answer_epoch = new Optional(),
        public int|Optional|null $end_epoch = new Optional(),

        public int|Optional|null $duration = new Optional(),

        public string|Optional|null $hangup_cause = new Optional(),
        public string|Optional|null $hangup_cause_q850 = new Optional(),

        public bool|Optional|null $voicemail_message = new Optional(),
        public string|Optional|null $cc_cancel_reason = new Optional(),
        public string|Optional|null $cc_cause = new Optional(),
        public string|Optional|null $sip_hangup_disposition = new Optional(),

        public string|Optional|null $status = new Optional(),
        public string|Optional|null $call_disposition = new Optional(),

        /** @var array<int, CdrCallFlowStepData>|null */
        public array|Optional|null $call_flow = new Optional(),
    ) {}
}
