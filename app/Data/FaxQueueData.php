<?php

namespace App\Data;

use App\Data\FaxFileData;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\Attributes\DataCollectionOf;

class FaxQueueData extends Data
{
    public function __construct(
        public string $fax_queue_uuid,
        public ?string $domain_uuid,
        public ?string $fax_caller_id_number,
        public ?string $fax_number,
        public ?string $fax_caller_id_number_formatted,
        public ?string $fax_number_formatted,
        public ?string $fax_date,
        public ?string $fax_status,
        // public ?array $fax_file,
        public ?string $fax_date_formatted,
        public ?string $fax_retry_date_formatted,
        public ?string $fax_notify_date_formatted,
    ) {}
}
