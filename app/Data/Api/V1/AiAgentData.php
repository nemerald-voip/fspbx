<?php

namespace App\Data\Api\V1;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

class AiAgentData extends Data
{
    public function __construct(
        public string $ai_agent_uuid,
        public string $object,
        public string $domain_uuid,

        public string $agent_name,
        public string $agent_extension,

        public bool|Optional|null $agent_enabled = new Optional(),

        public ?string $description = null,

        public string|Optional|null $voice_id = new Optional(),
        public string|Optional|null $language = new Optional(),
        public string|Optional|null $first_message = new Optional(),
        public string|Optional|null $system_prompt = new Optional(),

        public string|Optional|null $elevenlabs_agent_id = new Optional(),
        public string|Optional|null $elevenlabs_phone_number_id = new Optional(),
    ) {}
}
