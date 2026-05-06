<?php

namespace App\Http\Requests;

use App\Models\ConferenceCenter;
use App\Models\ConferenceRoom;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreConferenceRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return userCheckPermission('conference_room_add');
    }

    public function rules(): array
    {
        $roomUuid = $this->conferenceRoomUuid();
        $domainUuid = session('domain_uuid');
        $pinLength = $this->pinLength();

        $pinRules = [
            'nullable',
            'string',
            $pinLength > 0 ? "min:{$pinLength}" : null,
            function (string $attribute, mixed $value, \Closure $fail) use ($roomUuid, $domainUuid) {
                if (blank($value)) {
                    return;
                }

                $otherPinField = $attribute === 'moderator_pin' ? 'participant_pin' : 'moderator_pin';
                if ((string) $value === (string) $this->input($otherPinField)) {
                    $fail('Moderator and participant PINs must be different.');
                    return;
                }

                $exists = ConferenceRoom::query()
                    ->where('domain_uuid', $domainUuid)
                    ->when($roomUuid, fn ($query) => $query->where('conference_room_uuid', '!=', $roomUuid))
                    ->where(function ($query) use ($value) {
                        $query->where('moderator_pin', $value)
                            ->orWhere('participant_pin', $value);
                    })
                    ->exists();

                if ($exists) {
                    $fail('This PIN is already in use.');
                }
            },
        ];

        return [
            'conference_center_uuid' => [
                'required',
                'uuid',
                Rule::exists('v_conference_centers', 'conference_center_uuid')
                    ->where('domain_uuid', $domainUuid),
            ],
            'conference_room_name' => ['required', 'string', 'max:255'],
            'moderator_pin' => array_values(array_filter($pinRules)),
            'participant_pin' => array_values(array_filter($pinRules)),
            'profile' => ['nullable', 'string', 'max:255'],
            'record' => ['required', 'in:true,false'],
            'max_members' => ['nullable', 'integer', 'min:0'],
            'start_datetime' => ['nullable', 'string', 'max:255'],
            'stop_datetime' => ['nullable', 'string', 'max:255'],
            'wait_mod' => ['required', 'in:true,false'],
            'moderator_endconf' => ['required', 'in:true,false'],
            'announce_name' => ['required', 'in:true,false'],
            'announce_recording' => ['required', 'in:true,false'],
            'announce_count' => ['required', 'in:true,false'],
            'sounds' => ['required', 'in:true,false'],
            'mute' => ['required', 'in:true,false'],
            'email_address' => ['nullable', 'email', 'max:255'],
            'account_code' => ['nullable', 'string', 'max:255'],
            'enabled' => ['required', 'in:true,false'],
            'description' => ['nullable', 'string', 'max:255'],
            'user_uuid' => [
                'nullable',
                'uuid',
                Rule::exists('v_users', 'user_uuid')->where('domain_uuid', $domainUuid),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'moderator_pin' => preg_replace('/\D/', '', (string) $this->input('moderator_pin')),
            'participant_pin' => preg_replace('/\D/', '', (string) $this->input('participant_pin')),
            'profile' => $this->input('profile', 'default') ?: 'default',
            'record' => $this->input('record', 'false'),
            'max_members' => $this->input('max_members', 0),
            'wait_mod' => $this->input('wait_mod', 'true'),
            'moderator_endconf' => $this->input('moderator_endconf', 'false'),
            'announce_name' => $this->input('announce_name', 'true'),
            'announce_recording' => $this->input('announce_recording', 'true'),
            'announce_count' => $this->input('announce_count', 'true'),
            'sounds' => $this->input('sounds', 'false'),
            'mute' => $this->input('mute', 'false'),
            'enabled' => $this->input('enabled', 'true'),
        ]);
    }

    protected function conferenceRoomUuid(): ?string
    {
        return null;
    }

    private function pinLength(): int
    {
        $centerUuid = $this->input('conference_center_uuid');

        if (! is_string($centerUuid)) {
            return 0;
        }

        return (int) ConferenceCenter::query()
            ->where('domain_uuid', session('domain_uuid'))
            ->where('conference_center_uuid', $centerUuid)
            ->value('conference_center_pin_length');
    }
}
