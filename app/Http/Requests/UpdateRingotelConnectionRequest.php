<?php

namespace App\Http\Requests;

use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRingotelConnectionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'org_id' => 'present',
            'conn_id' => 'present',
            'connection_name' => 'required|string|max:100',
            'protocol' => 'required|string',
            'domain' => 'required|string',
            'port' => 'nullable|numeric',
            'dont_verify_server_certificate' => 'present',
            'disable_srtp' => 'present',
            'multitenant' => 'present',
            'proxy' => 'nullable|string',
            'codecs' => 'present|array',
            'codecs.*.name' => 'required|string',
            'codecs.*.enabled' => 'required|boolean',
            'codecs.*.frame' => 'required|numeric',
            'registration_ttl' => 'required|numeric',
            'max_registrations' => 'required|numeric',
            'app_opus_codec' => 'present',
            'one_push' => 'present',
            'show_call_settings' => 'present',
            'allow_call_recording' => 'present',
            'allow_state_change' => 'present',
            'allow_video_calls' => 'present',
            'allow_internal_chat' => 'present',
            'disable_iphone_recents' => 'present',
            'call_delay' => 'required|numeric',
            'desktop_app_delay' => 'present',
            'pbx_features' => 'present',
            'voicemail_extension' => 'nullable|string',
            'dnd_on_code' => 'nullable|string',
            'dnd_off_code' => 'nullable|string',
            'cf_on_code' => 'nullable|string',
            'cf_off_code' => 'nullable|string',
            'callpark' => 'present|array',
            'callpark.park' => 'nullable|string',
            'callpark.retrieve' => 'nullable|string',
            'callpark.subscribe' => 'nullable|string',
            'callpark.slots' => 'present|array',
            'callpark.slots.*.alias' => 'nullable|string|max:100',
            'callpark.slots.*.slot' => 'nullable|string|max:50',
            'sms' => 'nullable|integer|in:0,2,3',
            'sms2phone' => 'present',
            'sms2email' => 'present',
            'inboundFormat' => 'nullable|string',
            'custompages' => 'sometimes|array',
            'custompages.*.title' => 'nullable|string|max:100',
            'custompages.*.url' => 'nullable|string|max:2048',
            'callwaiting' => 'present|array',
            'callwaiting.on' => 'nullable|string',
            'callwaiting.off' => 'nullable|string',
            'remotehold' => 'present',
            'passanumber' => 'present',
            'fallback' => 'present|array',
            'fallback.prefix' => 'nullable|string',
            'fallback.type' => 'nullable|string',
            'speeddial' => 'present|array',
            'speeddial.*.title' => 'nullable|string|max:100',
            'speeddial.*.number' => 'nullable|string|max:50',
            'blfs' => 'present|array',
            'blfs.*.title' => 'nullable|string|max:100',
            'blfs.*.number' => 'nullable|string|max:50',
            'paging' => 'nullable|integer',
            'meetings' => 'nullable|integer',
            'integrations' => 'nullable|integer',
            'nologae' => 'present',
            'nologmc' => 'present',
            'noblocks' => 'present|boolean',
            'notranscription' => 'present',
            'screenshotPrevention' => 'present',
            'beta_updates' => 'present',
        ];
    }


    public function prepareForValidation(): void
    {
        // Check if 'region' is missing or empty and set it to null
        if (!$this->has('protocol') || $this->input('protocol') === 'NULL') {
            $this->merge(['protocol' => null]);
        }

        // if ($this->has('dont_send_user_credentials')) {
        //     $this->merge([
        //         'dont_send_user_credentials' => $this->dont_send_user_credentials ? 'true' : 'false',
        //     ]);
        // }

    }

    /**
     * Sanitize the input field to prevent XSS and remove unwanted characters.
     *
     * @param string $input
     * @return string
     */
    protected function sanitizeInput(string $input): string
    {
        // Trim whitespace
        $input = trim($input);

        // Strip HTML tags
        $input = strip_tags($input);

        // Escape special characters
        $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');

        // Remove any non-ASCII characters if necessary (optional)
        $input = preg_replace('/[^\x20-\x7E]/', '', $input);

        return $input;
    }


}
