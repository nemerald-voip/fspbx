<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\ValidationRule;


class ValidVoicemailPassword implements ValidationRule
{
    /**
     * Validate the attribute.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @param  \Closure  $fail
     * @return void
     */
    public function validate(string $attribute, mixed $value, \Closure $fail): void
    {
        // Check if password complexity is required
        if (get_domain_setting('password_complexity')) {

            // Check if password meets the minimum length
            $minLength = get_domain_setting('password_min_length');
            if (strlen($value) < $minLength) {
                $fail('The password must be at least ' . $minLength . ' characters long.');
                return;
            }

            // Check for repeating digits
            $repeatingPatterns = ['000', '111', '222', '333', '444', '555', '666', '777', '888', '999'];
            foreach ($repeatingPatterns as $pattern) {
                if (strpos($value, $pattern) !== false) {
                    $fail('The password cannot contain repeating digits');
                    return;
                }
            }

            // Check for sequential digits
            $sequentialPatterns = [
                '012', '123', '234', '345', '456', '567', '678', '789',
                '987', '876', '765', '654', '543', '432', '321', '210'
            ];
            foreach ($sequentialPatterns as $pattern) {
                if (strpos($value, $pattern) !== false) {
                    $fail('The password cannot contain sequential digits');
                    return;
                }
            }

        } else {
            // If password complexity is not enforced, ensure it meets default validation
            if (!is_numeric($value) || strlen($value) < 3 || strlen($value) > 10) {
                $fail('The password must be a numeric value between 3 and 10 digits.');
                return;
            }
        }
    }

}
