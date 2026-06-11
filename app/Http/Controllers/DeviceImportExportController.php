<?php

namespace App\Http\Controllers;

use Throwable;
use App\Exports\DevicesTemplate;
use App\Imports\DevicesPreviewImport;
use App\Models\DeviceKeyTemplate;
use App\Models\DeviceLines;
use App\Models\Devices;
use App\Models\Extensions;
use App\Models\ProvisioningTemplate;
use App\Services\DeviceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\HeadingRowImport;
use Maatwebsite\Excel\Excel as ExcelWriter;

class DeviceImportExportController extends Controller
{
    public function importPreview(): JsonResponse
    {
        if (! userCheckPermission('device_import')) {
            abort(403);
        }

        try {
            $file = request()->file('file');

            if (! $file) {
                return response()->json([
                    'success' => false,
                    'errors' => ['file' => ['Please select a CSV file.']],
                ], 422);
            }

            $headings = (new HeadingRowImport)->toArray($file)[0][0] ?? [];
            $forbiddenHeadings = array_intersect($headings, [
                'template',
                'device_template',
                'profile_or_key_template',
                'key_template',
                'profile',
            ]);

            if (! empty($forbiddenHeadings)) {
                return response()->json([
                    'success' => false,
                    'errors' => [
                        'server' => [
                            'Template and key template assignments are selected after upload. Remove these columns: '
                                . implode(', ', $forbiddenHeadings),
                        ],
                    ],
                ], 422);
            }

            $import = new DevicesPreviewImport;
            $rows = $import->toArray($file);

            if ($import->failures()->isNotEmpty()) {
                $errors = [];
                foreach ($import->failures() as $failure) {
                    foreach ($failure->errors() as $message) {
                        $errors[] = "Row {$failure->row()}, '{$failure->attribute()}': {$message}";
                    }
                }

                return response()->json([
                    'success' => false,
                    'errors' => ['server' => $errors],
                ], 422);
            }

            $previewData = [];
            foreach (($rows[0] ?? []) as $row) {
                $previewData[] = [
                    'id' => Str::uuid()->toString(),
                    'mac_address' => $row['mac_address'] ?? null,
                    'serial_number' => $row['serial_number'] ?? null,
                    'associated_extension' => $row['associated_extension'] ?? null,
                    'device_template' => null,
                    'device_key_template_uuid' => null,
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $previewData,
            ], 200);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'errors' => ['server' => [$e->getMessage()]],
            ], 422);
        } catch (Throwable $e) {
            logger('DeviceImportExportController@importPreview error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());

            return response()->json([
                'success' => false,
                'errors' => ['server' => [$e->getMessage()]],
            ], 500);
        }
    }

    public function importCommit(Request $request): JsonResponse
    {
        if (! userCheckPermission('device_import')) {
            abort(403);
        }

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.mac_address' => [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    $normalized = strtolower(preg_replace('/[^0-9a-f]/i', '', (string) $value));

                    if (strlen($normalized) !== 12) {
                        $fail('The MAC address is invalid.');
                    }
                },
            ],
            'items.*.serial_number' => ['nullable', 'string'],
            'items.*.associated_extension' => [
                'nullable',
                Rule::exists('v_extensions', 'extension')->where('domain_uuid', session('domain_uuid')),
            ],
            'items.*.device_template' => ['nullable', 'string'],
            'items.*.device_key_template_uuid' => ['nullable', 'string'],
        ]);

        try {
            DB::beginTransaction();

            foreach ($validated['items'] as $row) {
                $this->commitDeviceRow($row);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'messages' => ['success' => [count($validated['items']) . ' devices imported successfully.']],
            ], 200);
        } catch (\InvalidArgumentException $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'errors' => ['server' => [$e->getMessage()]],
            ], 422);
        } catch (Throwable $e) {
            DB::rollBack();
            logger('DeviceImportExportController@importCommit error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());

            return response()->json([
                'success' => false,
                'errors' => ['server' => [$e->getMessage()]],
            ], 500);
        }
    }

    public function downloadTemplate()
    {
        if (! userCheckPermission('device_import')) {
            abort(403);
        }

        return Excel::download(new DevicesTemplate, 'devices_template.csv', ExcelWriter::CSV);
    }

    private function commitDeviceRow(array $row): void
    {
        $domainUuid = session('domain_uuid');
        $macAddress = $this->normalizeMac((string) ($row['mac_address'] ?? ''));
        $serialNumber = $this->normalizeSerial($row['serial_number'] ?? null);
        $templateInput = trim((string) ($row['device_template'] ?? ''));
        $keyTemplateInput = trim((string) ($row['device_key_template_uuid'] ?? ''));
        $templateColumns = $this->resolveDeviceTemplateColumns($templateInput);
        $keyTemplateUuid = $this->resolveKeyTemplateUuid($keyTemplateInput);
        $linePayload = ! empty($row['associated_extension'])
            ? $this->linePayload((string) $row['associated_extension'])
            : null;

        $device = Devices::where('domain_uuid', $domainUuid)
            ->where('device_address', $macAddress)
            ->first();

        $payload = [
            'domain_uuid' => $domainUuid,
            'device_address' => formatMacAddress($macAddress),
            'device_address_modified' => $macAddress,
            'serial_number' => $serialNumber,
            'device_enabled' => 'true',
        ];

        if (! $device || $templateInput !== '') {
            $payload['device_template'] = $templateColumns['device_template'];
            $payload['device_template_uuid'] = $templateColumns['device_template_uuid'];
            $payload['device_vendor'] = $templateColumns['device_vendor'];
        }

        if (! $device || $keyTemplateInput !== '') {
            $payload['device_key_template_uuid'] = $keyTemplateUuid;
            $payload['device_profile_uuid'] = null;
        }

        if (! empty($row['associated_extension'])) {
            $payload['device_label'] = $row['associated_extension'];
        }

        if ($device) {
            app(DeviceService::class)->update($device, $payload);
            $this->syncLineOne($device->fresh(), $linePayload);
            return;
        }

        if ($linePayload) {
            $payload['device_lines'] = [$linePayload];
        }

        app(DeviceService::class)->create($payload);
    }

    private function resolveDeviceTemplateColumns(?string $rawTemplate): array
    {
        $rawTemplate = trim((string) $rawTemplate);

        if ($rawTemplate === '' || $rawTemplate === 'NULL') {
            return [
                'device_template' => null,
                'device_template_uuid' => null,
                'device_vendor' => null,
            ];
        }

        if (Str::isUuid($rawTemplate)) {
            $template = ProvisioningTemplate::query()
                ->where('template_uuid', $rawTemplate)
                ->first();

            if (! $template) {
                throw new \InvalidArgumentException('Selected template was not found.');
            }

            return [
                'device_template' => null,
                'device_template_uuid' => $rawTemplate,
                'device_vendor' => $template->vendor,
            ];
        }

        $vendor = null;
        if (str_contains($rawTemplate, '/')) {
            [$vendor] = explode('/', $rawTemplate, 2);
            $vendor = strtolower($vendor);
            if ($vendor === 'poly') {
                $vendor = 'polycom';
            }
        }

        return [
            'device_template' => $rawTemplate,
            'device_template_uuid' => null,
            'device_vendor' => $vendor,
        ];
    }

    private function resolveKeyTemplateUuid(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '' || $value === 'NULL') {
            return null;
        }

        if (! userCheckPermission('device_key_template_assign')) {
            throw new \InvalidArgumentException('Access denied for assigning key templates.');
        }

        $template = DeviceKeyTemplate::query()
            ->where('domain_uuid', session('domain_uuid'))
            ->where('device_key_template_uuid', $value)
            ->first();

        if (! $template) {
            throw new \InvalidArgumentException('Selected key template was not found.');
        }

        return $template->device_key_template_uuid;
    }

    private function linePayload(string $extensionNumber): array
    {
        $domainUuid = session('domain_uuid');
        $extension = Extensions::where('domain_uuid', $domainUuid)
            ->where('extension', $extensionNumber)
            ->firstOrFail();

        return [
            'line_type_id' => 'line',
            'line_number' => '1',
            'server_address' => session('domain_name'),
            'server_address_primary' => get_domain_setting('server_address_primary', $domainUuid),
            'server_address_secondary' => get_domain_setting('server_address_secondary', $domainUuid),
            'outbound_proxy_primary' => get_domain_setting('outbound_proxy_primary', $domainUuid),
            'outbound_proxy_secondary' => get_domain_setting('outbound_proxy_secondary', $domainUuid),
            'display_name' => $extension->extension,
            'user_id' => $extension->extension,
            'auth_id' => $extension->extension,
            'label' => $extension->extension,
            'password' => $extension->password,
            'sip_port' => get_domain_setting('line_sip_port', $domainUuid),
            'sip_transport' => get_domain_setting('line_sip_transport', $domainUuid),
            'register_expires' => get_domain_setting('line_register_expires', $domainUuid),
            'domain_uuid' => $domainUuid,
        ];
    }

    private function syncLineOne(Devices $device, ?array $linePayload): void
    {
        $existingLine = DeviceLines::where('domain_uuid', session('domain_uuid'))
            ->where('device_uuid', $device->device_uuid)
            ->where('line_number', '1')
            ->first();

        if (! $linePayload) {
            $existingLine?->delete();
            return;
        }

        $linePayload['device_uuid'] = $device->device_uuid;
        $linePayload['device_line_uuid'] = $existingLine?->device_line_uuid;

        $line = $existingLine ?: new DeviceLines();
        $line->fill($linePayload);
        $line->save();
    }

    private function normalizeMac(string $mac): string
    {
        return strtolower(preg_replace('/[^0-9a-f]/i', '', $mac));
    }

    private function normalizeSerial(mixed $serial): ?string
    {
        $serial = strtolower(preg_replace('/[^a-z0-9]/i', '', (string) $serial));

        return $serial === '' ? null : $serial;
    }
}
