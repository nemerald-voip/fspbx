<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use App\Data\MobileAppData;

class ExtensionListData extends Data
{
    public function __construct(
        public string $extension_uuid,
        public string $extension,
        public ?string $effective_caller_id_name,
        public ?string $effective_caller_id_number,
        public ?string $outbound_caller_id_number_e164,
        public ?string $outbound_caller_id_number_formatted,
        public ?string $emergency_caller_id_number_e164,
        public ?string $directory_first_name,
        public ?string $directory_last_name,
        public ?string $name_formatted,
        public ?string $directory_visible,
        public ?string $directory_exten_visible,
        public ?string $email,
        public ?string $enabled,
        public ?string $do_not_disturb,
        public ?string $user_record,
        public ?bool $suspended,
        public ?string $description,
        public ?string $forward_all_enabled,
        public ?string $forward_busy_enabled,
        public ?string $forward_no_answer_enabled,
        public ?string $forward_user_not_registered_enabled,
        public ?string $follow_me_enabled,

        public ?MobileAppData $mobile_app,
    ) {}
}

