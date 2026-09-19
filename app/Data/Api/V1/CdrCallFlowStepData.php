<?php

namespace App\Data\Api\V1;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

class CdrCallFlowStepData extends Data
{
    public function __construct(
        public string|Optional|null $destination_number = new Optional(),
        public string|Optional|null $context = new Optional(),

        public string|int|Optional|null $bridged_time = new Optional(),
        public string|int|Optional|null $created_time = new Optional(),
        public string|int|Optional|null $answered_time = new Optional(),
        public string|int|Optional|null $progress_time = new Optional(),
        public string|int|Optional|null $transfer_time = new Optional(),
        public string|int|Optional|null $profile_created_time = new Optional(),
        public string|int|Optional|null $profile_end_time = new Optional(),
        public string|int|Optional|null $progress_media_time = new Optional(),
        public string|int|Optional|null $hangup_time = new Optional(),

        public int|Optional|null $duration_seconds = new Optional(),
        public string|Optional|null $duration_formatted = new Optional(),

        public string|Optional|null $call_disposition = new Optional(),
        public string|Optional|null $time_line = new Optional(),

        public string|Optional|null $dialplan_app = new Optional(),
        public string|Optional|null $dialplan_name = new Optional(),
        public string|Optional|null $dialplan_description = new Optional(),

        public string|Optional|null $xml_cdr_uuid = new Optional(),
        public string|Optional|null $cc_callback_attempt_uuid = new Optional(),
        public string|Optional|null $cc_callback_role = new Optional(),
        public string|Optional|null $status = new Optional(),
        public int|Optional|null $billsec = new Optional(),
        public int|Optional|null $waitsec = new Optional(),
        public string|Optional|null $queue_result = new Optional(),
    ) {}
}
