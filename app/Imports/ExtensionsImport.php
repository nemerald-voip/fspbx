<?php

namespace App\Imports;

use App\Models\Devices;
use App\Models\Extensions;
use App\Models\Voicemails;
use App\Models\DeviceLines;
use App\Models\FusionCache;
use App\Rules\UniqueExtension;
use Illuminate\Validation\Rule;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsErrors;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use App\Models\DomainSettings;
use App\Models\DefaultSettings;

class ExtensionsImport implements ToCollection, WithHeadingRow, SkipsEmptyRows, SkipsOnError, SkipsOnFailure, WithValidation
{
    use Importable, SkipsErrors, SkipsFailures;

    protected string $domain_uuid;
    protected bool $defaultVoicemailEnabled;

    public function __construct(string $domain_uuid)
    {
        $this->domain_uuid = $domain_uuid;
        $this->defaultVoicemailEnabled = $this->resolveDefaultVoicemailEnabled($domain_uuid);
    }

    public function rules(): array
    {
        return [
            '*.extension' => [
                'required',
                'numeric',
                new UniqueExtension(),
            ],
            '*.first_name' => [
                'required',
                'string'
            ],
            '*.last_name' => [
                'nullable',
                'string'
            ],
            '*.outbound_caller_id_number' => [
                'nullable'
            ],
            '*.emergency_caller_id_number' => [
                'nullable'
            ],
            '*.description' => [
                'string',
                'nullable'
            ],
            '*.email' => [
                'nullable',
                'email:rfc',
            ],
            '*.voicemail_enabled' => [
                'nullable',
                Rule::in(['true', 'false', '1', '0', 'yes', 'no', 'on', 'off'])
            ],
            '*.device_address' => [
                'mac_address',
                'nullable',
            ],
            '*.device_address_modified' => [
                'nullable',
                Rule::unique('App\Models\Devices', 'device_address')
            ],
            '*.device_vendor' => [
                'string',
                'nullable'
            ],
            '*.device_template' => [
                'string',
                'nullable'
            ]
        ];
    }

    public function customValidationMessages()
    {
        return [
            'extension.numeric' => 'Extension must only contain numeric values',
            'outbound_caller_id_number.phone' => 'The :attribute is not a valid US number',
            'device_address_modified.unique' => 'Duplicate MAC address has been found',
            'voicemail_enabled.in' => 'Voicemail enabled must be one of: true, false, 1, 0, yes, no, on, off',
        ];
    }

    public function prepareForValidation($data, $index)
    {
        $data['extension'] = trim((string)($data['extension'] ?? ''));
        $data['first_name'] = trim((string)($data['first_name'] ?? ''));
        $data['last_name'] = trim((string)($data['last_name'] ?? ''));
        $data['description'] = trim((string)($data['description'] ?? ''));
        $data['device_vendor'] = trim((string)($data['device_vendor'] ?? ''));
        $data['device_template'] = trim((string)($data['device_template'] ?? ''));
        $data['email'] = strtolower(trim((string)($data['email'] ?? '')));

        if (array_key_exists('voicemail_enabled', $data)) {
            $voicemailEnabled = strtolower(trim((string)($data['voicemail_enabled'] ?? '')));
            $data['voicemail_enabled'] = $voicemailEnabled === '' ? null : $voicemailEnabled;
        }

        if (!empty($data['device_address'])) {
            $deviceAddress = trim((string)$data['device_address']);
            $deviceAddress = str_replace(':', '', $deviceAddress);
            $deviceAddress = str_replace('-', '', $deviceAddress);
            $deviceAddress = strtolower($deviceAddress);

            $data['device_address_modified'] = $deviceAddress;
            $data['device_address'] = strtolower(implode(':', str_split($deviceAddress, 2)));
        } else {
            $data['device_address'] = null;
            $data['device_address_modified'] = null;
        }

        if (isset($data['outbound_caller_id_number'])) {
            $data['outbound_caller_id_number'] = preg_replace('/[^0-9+]/', '', (string) $data['outbound_caller_id_number']);
        }

        if (isset($data['emergency_caller_id_number'])) {
            $data['emergency_caller_id_number'] = preg_replace('/[^0-9+]/', '', (string) $data['emergency_caller_id_number']);
        }

        return $data;
    }

    public function customValidationAttributes()
    {
        return [
            'device_address_modified' => 'device_address',
        ];
    }

    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            DB::beginTransaction();

            try {
                $voicemailEnabled = $this->resolveRowVoicemailEnabled($row);

                $extension = Extensions::create([
                    'domain_uuid' => session('domain_uuid'),
                    'extension' => $row['extension'],
                    'password' => generate_password(),
                    'directory_first_name' => $row['first_name'],
                    'directory_last_name' => $row['last_name'] ?? null,
                    'effective_caller_id_name' => trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')),
                    'effective_caller_id_number' => $row['extension'] ?? null,
                    'outbound_caller_id_number' => $row['outbound_caller_id_number'] ?? null,
                    'emergency_caller_id_number' => $row['emergency_caller_id_number'] ?? null,
                    'description' => $row['description'] ?? null,
                    'directory_visible' => 'true',
                    'directory_exten_visible' => 'true',
                    'limit_max' => '5',
                    'limit_destination' => '!USER_BUSY',
                    'user_context' => session('domain_name'),
                    'accountcode' => session('domain_name'),
                    'call_timeout' => 25,
                    'call_screen_enabled' => 'false',
                    'force_ping' => 'false',
                    'enabled' => 'true',
                ]);

                if ($voicemailEnabled) {
                    if (get_domain_setting('password_complexity')) {
                        $voicemail_password = str_pad(mt_rand(0, 9999), 4, '0', STR_PAD_LEFT);
                    } else {
                        $voicemail_password = $row['extension'];
                    }

                    $extension->voicemail = new Voicemails();
                    $extension->voicemail->fill([
                        'domain_uuid' => session('domain_uuid'),
                        'voicemail_id' => $row['extension'],
                        'voicemail_password' => $voicemail_password,
                        'voicemail_mail_to' => $row['email'] ?? null,
                        'voicemail_transcription_enabled' => 'true',
                        'voicemail_recording_instructions' => 'true',
                        'voicemail_file' => 'attach',
                        'voicemail_local_after_email' => 'true',
                        'voicemail_tutorial' => 'true',
                        'voicemail_enabled' => 'true',
                    ]);
                    $extension->voicemail->save();
                }

                if (!empty($row['device_address'])) {
                    $deviceAddressNoColons = str_replace(':', '', $row['device_address']);

                    $device = new Devices();
                    $device->fill([
                        'domain_uuid' => session('domain_uuid'),
                        'device_address' => $deviceAddressNoColons,
                        'device_label' => $row['extension'],
                        'device_vendor' => $row['device_vendor'] ?? null,
                        'device_enabled' => 'true',
                        'device_enabled_date' => date('Y-m-d H:i:s'),
                        'device_template' => $row['device_template'] ?? null,
                        'device_description' => $row['description'] ?? null,
                    ]);
                    $device->save();

                    $device->lines = new DeviceLines();
                    $device->lines->fill([
                        'domain_uuid' => session('domain_uuid'),
                        'device_uuid' => $device->device_uuid,
                        'line_number' => '1',
                        'server_address' => Session::get('domain_name'),
                        'server_address_primary' => get_domain_setting('server_address_primary'),
                        'server_address_secondary' => get_domain_setting('server_address_secondary'),
                        'outbound_proxy_primary' => get_domain_setting('outbound_proxy_primary'),
                        'outbound_proxy_secondary' => get_domain_setting('outbound_proxy_secondary'),
                        'display_name' => $row['extension'],
                        'user_id' => $row['extension'],
                        'auth_id' => $row['extension'],
                        'label' => $row['extension'],
                        'password' => $extension->password,
                        'sip_port' => get_domain_setting('line_sip_port'),
                        'sip_transport' => get_domain_setting('line_sip_transport'),
                        'register_expires' => get_domain_setting('line_register_expires'),
                        'enabled' => 'true',
                    ]);

                    $device->lines->save();
                }

                if (session_status() == PHP_SESSION_NONE || session_id() == '') {
                    session_start();
                }

                FusionCache::clear("directory:" . $extension->extension . "@" . $extension->user_context);

                if (isset($_SESSION['destinations']['array'])) {
                    unset($_SESSION['destinations']['array']);
                }

                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();
                throw $e;
            }
        }
    }

    protected function resolveDefaultVoicemailEnabled(string $domain_uuid): bool
    {
        $voicemailEnabledSetting = DomainSettings::query()
            ->where('domain_uuid', $domain_uuid)
            ->where('domain_setting_category', 'voicemail')
            ->where('domain_setting_subcategory', 'enabled_default')
            ->where('domain_setting_enabled', 'true')
            ->value('domain_setting_value');

        if ($voicemailEnabledSetting === null) {
            $voicemailEnabledSetting = DefaultSettings::query()
                ->where('default_setting_category', 'voicemail')
                ->where('default_setting_subcategory', 'enabled_default')
                ->where('default_setting_enabled', 'true')
                ->value('default_setting_value');
        }

        return filter_var($voicemailEnabledSetting, FILTER_VALIDATE_BOOLEAN);
    }

    protected function resolveRowVoicemailEnabled($row): bool
    {
        if (!array_key_exists('voicemail_enabled', $row->toArray())) {
            return $this->defaultVoicemailEnabled;
        }

        $value = $row['voicemail_enabled'];

        if ($value === null || $value === '') {
            return $this->defaultVoicemailEnabled;
        }

        return in_array(strtolower((string)$value), ['true', '1', 'yes', 'on'], true);
    }
}