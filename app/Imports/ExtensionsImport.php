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

class ExtensionsImport implements ToCollection, WithHeadingRow, SkipsEmptyRows, SkipsOnError, SkipsOnFailure, WithValidation
{
    use Importable, SkipsErrors, SkipsFailures;

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
            '*.outbound_caller_id_number' => [
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

    /**
     * @return array
     */
    public function customValidationMessages()
    {
        return [
            'extension.numeric' => 'Extension must only contain numeric values',
            'outbound_caller_id_number.phone' => 'The :attribute is not a valid US number',
            'device_address_modified.unique' => 'Duplicate MAC address has been found',
        ];
    }


    public function prepareForValidation($data, $index)
    {
        $data['device_address'] = trim(str_replace(':', '', $data['device_address']));
        $data['device_address'] = trim(str_replace('-', '', $data['device_address']));
        $data['device_address_modified'] = strtolower(trim($data['device_address']));
        $data['extension'] = trim($data['extension']);
        $data['device_address'] = strtolower(trim(implode(":", str_split($data['device_address'], 2))));
        $data['extension'] = trim($data['extension']);
        $data['first_name'] = trim($data['first_name']);
        $data['last_name'] = trim($data['last_name'] ?? '');
        $data['description'] = trim($data['description'] ?? '');
        $data['device_vendor'] = trim($data['device_vendor'] ?? '');
        $data['device_template'] = trim($data['device_template'] ?? '');
        $data['email' ] = strtolower(trim($data['email'] ?? ''));
        if (isset($data['outbound_caller_id_number'])) {
            $data['outbound_caller_id_number'] = preg_replace('/[^0-9+]/', '', (string) $data['outbound_caller_id_number']);
        }
        if (isset($data['emergency_caller_id_number'])) {
            $data['emergency_caller_id_number'] = preg_replace('/[^0-9+]/', '', (string) $data['emergency_caller_id_number']);
        }
        return $data;
    }

    /**
     * @return array
     */
    public function customValidationAttributes()
    {
        return [
            'device_address_modified' => 'device_address',
        ];
    }

    /**
     * @param array $row
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function collection(Collection $rows)
    {

        // Validator::make($rows->toArray(), [
        //     '*.extension' => 'required|numeric',
        // ])->validate();

        foreach ($rows as $row) {

            //Create extension
            $extension = Extensions::create([
                'domain_uuid' => session('domain_uuid'),
                'extension' => $row['extension'],
                'password' => generate_password(),
                'directory_first_name' => $row['first_name'],
                'directory_last_name' => $row['last_name'] ?? null,
                'effective_caller_id_name' => trim($row['first_name'] . ' ' . $row['last_name']),
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

            //Create voicemail
            $extension->voicemail = new Voicemails();
            if (get_domain_setting('password_complexity')) {
                $voicemail_password = str_pad(mt_rand(0, 9999), 4, '0', STR_PAD_LEFT);
            } else {
                $voicemail_password = $row['extension'];
            }
            $extension->voicemail->fill([
                'domain_uuid' => session('domain_uuid'),
                'voicemail_id' => $row['extension'],
                'voicemail_password' => $voicemail_password,
                'voicemail_mail_to' => $row['email'],
                'voicemail_transcription_enabled' => 'true',
                'voicemail_recording_instructions' => 'true',
                'voicemail_file' => 'attach',
                'voicemail_local_after_email' => 'true',
                'voicemail_tutorial' => 'true',
                'voicemail_enabled' => 'true',
            ]);
            $extension->voicemail->save();

            //Create device
            if (isset($row['device_address']) && !empty($row['device_address'])) {
                //Convert Mac address xx:xx:xx:xx:xx:xx to string xxxxxxxxxxxx
                $row['device_address'] = str_replace(':', '', $row['device_address']);
                $device = new Devices();
                $device->fill([
                    'domain_uuid' => session('domain_uuid'),
                    'device_address' => $row['device_address'],
                    'device_label' => $row['extension'],
                    'device_vendor' => $row['device_vendor'],
                    'device_enabled' => 'true',
                    'device_enabled_date' => date('Y-m-d H:i:s'),
                    'device_template' => $row['device_template'],
                    'device_description' => $row['description'],
                ]);
                $device->save();

                // Create device lines
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

            if (session_status() == PHP_SESSION_NONE  || session_id() == '') {
                session_start();
            }

            //clear fusionpbx cache
            FusionCache::clear("directory:" . $extension->extension . "@" . $extension->user_context);

            //clear the destinations session array
            if (isset($_SESSION['destinations']['array'])) {
                unset($_SESSION['destinations']['array']);
            }

            // log::alert (get_domain_setting('server_address_primary'));


        }
        // Log::alert($extension);
        // return $extension;

    }
}
