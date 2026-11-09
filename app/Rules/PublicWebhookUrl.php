<?php

namespace App\Rules;

use App\Services\CallWebhooks\PublicWebhookUrlValidator;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use InvalidArgumentException;

class PublicWebhookUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            app(PublicWebhookUrlValidator::class)->validateAndResolve((string) $value);
        } catch (InvalidArgumentException $exception) {
            $fail($exception->getMessage());
        }
    }
}
