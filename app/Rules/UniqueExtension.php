<?php

namespace App\Rules;

use Closure;
use App\Models\Dialplans;
use App\Models\Extensions;
use App\Models\Voicemails;
use Illuminate\Contracts\Validation\ValidationRule;

class UniqueExtension implements ValidationRule
{
    protected $domainUuid;

    public function __construct()
    {
        // Set the domain UUID from the session
        $this->domainUuid = session('domain_uuid');
    }

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
        // Check if the extension exists in the Extensions table with the same domain_uuid
        if (Extensions::where('extension', $value)
            ->where('domain_uuid', $this->domainUuid)
            ->exists()) {
            $fail('This extension is already in use.');
            return;
        }

        // Check if the extension exists in the Voicemails table with the same domain_uuid
        if (Voicemails::where('voicemail_id', $value)
            ->where('domain_uuid', $this->domainUuid)
            ->exists()) {
            $fail('This extension is already in use.');
            return;
        }

        // Check if the extension exists in the Dialplans table with the same domain_uuid
        if (Dialplans::where('dialplan_number', $value)
            ->where('domain_uuid', $this->domainUuid)
            ->exists()) {
            $fail('This extension is already in use.');
            return;
        }
    }
}
