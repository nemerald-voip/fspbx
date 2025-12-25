<?php

namespace App\Data\Api\V1;

use Spatie\LaravelData\Data;

class ExtensionData extends Data
{
    public function __construct(
        /** UUID of the extension */
        public string $extension_uuid,

        /** Always "extension" */
        public string $object,

        /** UUID of the domain this extension belongs to */
        public string $domain_uuid,

        /** Extension number (e.g., "1001") */
        public string $extension,

        /** Whether the extension is enabled */
        public bool $enabled,

        /** Caller ID name */
        public ?string $effective_caller_id_name,

        /** Caller ID number */
        public ?string $effective_caller_id_number,

        /** Outbound caller ID in E.164 format */
        public ?string $outbound_caller_id_number_e164,

        /** Outbound caller ID formatted (pretty/US formatting, etc.) */
        public ?string $outbound_caller_id_number_formatted,

        /** Emergency caller ID in E.164 format */
        public ?string $emergency_caller_id_number_e164,

        /** Directory first name */
        public ?string $directory_first_name,

        /** Directory last name */
        public ?string $directory_last_name,

        /** Full name (formatted) */
        public ?string $name_formatted,

        /** Directory is visible */
        public ?bool $directory_visible,

        /** Directory extension is visible */
        public ?bool $directory_exten_visible,

        /** Email address */
        public ?string $email,

        /** Do not disturb enabled */
        public ?bool $do_not_disturb,

        /** User record enabled */
        public ?bool $user_record,

        /** Extension suspended (admin state) */
        public ?bool $suspended,

        /** Description/label */
        public ?string $description,

        /** Forward all enabled */
        public ?bool $forward_all_enabled,

        /** Forward busy enabled */
        public ?bool $forward_busy_enabled,

        /** Forward no-answer enabled */
        public ?bool $forward_no_answer_enabled,

        /** Forward user-not-registered enabled */
        public ?bool $forward_user_not_registered_enabled,

        /** Follow-me enabled */
        public ?bool $follow_me_enabled,
    ) {}
}
