<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use App\Models\FollowMeDestinations;

class FollowMeDestinationData extends Data
{
    public function __construct(
        public string $destination,
        public int $delay,
        public int $timeout,
        public bool $prompt,
        public int $order,
    ) {}

    public static function fromModel(FollowMeDestinations $model): self
    {
        return new self(
            destination: $model->follow_me_destination,
            delay: (int) $model->follow_me_delay,
            timeout: (int) $model->follow_me_timeout,
            prompt: filter_var($model->follow_me_prompt, FILTER_VALIDATE_BOOLEAN),
            order: (int) $model->follow_me_order,
        );
    }
}
