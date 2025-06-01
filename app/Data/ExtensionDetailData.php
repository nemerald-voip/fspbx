<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use App\Data\FollowMeDestinationData;

class ExtensionDetailData extends Data
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
        public ?string $call_timeout,
        public ?string $call_screen_enabled,
        public ?string $max_registrations,
        public ?string $limit_max,
        public ?string $limit_destination,
        public ?string $toll_allow,
        public ?string $call_group,
        public ?bool $suspended,
        public ?string $description,

        // Forwarding: Unconditional
        public ?string $forward_all_enabled,
        public ?string $forward_all_target_uuid,
        public ?string $forward_all_action,
        public ?string $forward_all_action_display,
        public ?string $forward_all_target_name,
        public ?string $forward_all_target_extension,

        // Forwarding: Busy
        public ?string $forward_busy_enabled,
        public ?string $forward_busy_target_uuid,
        public ?string $forward_busy_action,
        public ?string $forward_busy_action_display,
        public ?string $forward_busy_target_name,
        public ?string $forward_busy_target_extension,

        // Forwarding: No Answer
        public ?string $forward_no_answer_enabled,
        public ?string $forward_no_answer_target_uuid,
        public ?string $forward_no_answer_action,
        public ?string $forward_no_answer_action_display,
        public ?string $forward_no_answer_target_name,
        public ?string $forward_no_answer_target_extension,

        // Forwarding: User Not Registered
        public ?string $forward_user_not_registered_enabled,
        public ?string $forward_user_not_registered_target_uuid,
        public ?string $forward_user_not_registered_action,
        public ?string $forward_user_not_registered_action_display,
        public ?string $forward_user_not_registered_target_name,
        public ?string $forward_user_not_registered_target_extension,

        // --- Follow Me ---
        public ?string $follow_me_enabled,
        /** @var FollowMeDestinationData[]|null */
        public ?array $follow_me_destinations,
    ) {}
}
