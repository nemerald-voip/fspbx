<?php

namespace App\Rules;

use App\Services\PhoneNumberService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidPhoneNumber implements ValidationRule
{
    public function __construct(private ?string $domainUuid = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! app(PhoneNumberService::class)->isValidForDomain($value, $this->domainUuid)) {
            $fail(__('Enter a valid phone number. Use + and the country code for international numbers.'));
        }
    }
}
