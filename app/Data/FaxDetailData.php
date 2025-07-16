<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\Attributes\DataCollectionOf;

class FaxDetailData extends Data
{
    public function __construct(
        public string  $fax_uuid,
        public string  $domain_uuid,
        public ?string $fax_email,
        public ?string $fax_name,
        public ?string $fax_extension,
        public ?string $fax_prefix,
        public ?string $fax_destination_number,
        public ?string $fax_caller_id_name,
        public ?string $fax_caller_id_number,
        public ?string $fax_description,
        public ?string $fax_toll_allow,
        public ?string $fax_forward_number,
        public ?int    $fax_send_channels,

        #[DataCollectionOf(FaxAllowedEmailData::class)]
        public ?DataCollection $allowed_emails = null,

        #[DataCollectionOf(FaxAllowedDomainNameData::class)]
        public ?DataCollection $allowed_domain_names = null,
    ) {}
}
