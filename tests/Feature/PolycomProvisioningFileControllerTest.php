<?php

namespace Tests\Feature;

use App\Http\Controllers\PolycomProvisioningFileController;
use App\Models\Devices;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PolycomProvisioningFileControllerTest extends TestCase
{
    public function test_put_stores_polycom_upload_in_bucket_path(): void
    {
        Storage::fake('polycom_provisioning_uploads');

        $response = $this->callController('PUT', 'calls', 'f04ea4691c59', 'calls', 'xml', 'call-list');

        $this->assertSame(200, $response->getStatusCode());
        Storage::disk('polycom_provisioning_uploads')->assertExists('calls/f04ea4691c59-calls.xml');
        $this->assertSame(
            'call-list',
            Storage::disk('polycom_provisioning_uploads')->get('calls/f04ea4691c59-calls.xml')
        );
    }

    public function test_log_put_appends_to_existing_log_file(): void
    {
        Storage::fake('polycom_provisioning_uploads');
        Storage::disk('polycom_provisioning_uploads')->put('logs/f04ea4691c59-app.log', "first\n");

        $response = $this->callController('PUT', 'logs', 'f04ea4691c59', 'app', 'log', "second\n");

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(
            "first\nsecond\n",
            Storage::disk('polycom_provisioning_uploads')->get('logs/f04ea4691c59-app.log')
        );
    }

    public function test_log_put_restarts_file_when_append_would_exceed_limit(): void
    {
        Storage::fake('polycom_provisioning_uploads');
        Storage::disk('polycom_provisioning_uploads')->put(
            'logs/f04ea4691c59-app.log',
            str_repeat('a', 524287)
        );

        $response = $this->callController('PUT', 'logs', 'f04ea4691c59', 'app', 'log', 'new-log');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(
            'new-log',
            Storage::disk('polycom_provisioning_uploads')->get('logs/f04ea4691c59-app.log')
        );
    }

    public function test_non_log_put_replaces_existing_file(): void
    {
        Storage::fake('polycom_provisioning_uploads');
        Storage::disk('polycom_provisioning_uploads')->put('phoneconfigs/f04ea4691c59-phone.cfg', 'old');

        $response = $this->callController('PUT', 'phoneconfigs', 'f04ea4691c59', 'phone', 'cfg', 'new');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(
            'new',
            Storage::disk('polycom_provisioning_uploads')->get('phoneconfigs/f04ea4691c59-phone.cfg')
        );
    }

    public function test_get_returns_stored_polycom_upload(): void
    {
        Storage::fake('polycom_provisioning_uploads');
        Storage::disk('polycom_provisioning_uploads')->put('calls/f04ea4691c59-calls.xml', 'stored-call-list');

        $response = $this->callController('GET', 'calls', 'f04ea4691c59', 'calls', 'xml');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('stored-call-list', $response->getContent());
    }

    public function test_head_checks_stored_polycom_upload(): void
    {
        Storage::fake('polycom_provisioning_uploads');
        Storage::disk('polycom_provisioning_uploads')->put('calls/f04ea4691c59-calls.xml', 'stored-call-list');

        $response = $this->callController('HEAD', 'calls', 'f04ea4691c59', 'calls', 'xml');

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_rejects_wrong_vendor(): void
    {
        Storage::fake('polycom_provisioning_uploads');

        $response = $this->callController(
            'PUT',
            'calls',
            'f04ea4691c59',
            'calls',
            'xml',
            'call-list',
            new Devices([
                'device_vendor' => 'yealink',
                'device_address' => 'f04ea4691c59',
            ])
        );

        $this->assertSame(404, $response->getStatusCode());
        Storage::disk('polycom_provisioning_uploads')->assertMissing('calls/f04ea4691c59-calls.xml');
    }

    public function test_rejects_wrong_mac(): void
    {
        Storage::fake('polycom_provisioning_uploads');

        $response = $this->callController('PUT', 'calls', '0004f28ad079', 'calls', 'xml', 'call-list');

        $this->assertSame(404, $response->getStatusCode());
        Storage::disk('polycom_provisioning_uploads')->assertMissing('calls/0004f28ad079-calls.xml');
    }

    public function test_rejects_unknown_bucket(): void
    {
        Storage::fake('polycom_provisioning_uploads');

        $response = $this->callController('PUT', 'unknown', 'f04ea4691c59', 'calls', 'xml', 'call-list');

        $this->assertSame(404, $response->getStatusCode());
    }

    public function test_rejects_unsafe_filename_parts(): void
    {
        Storage::fake('polycom_provisioning_uploads');

        $response = $this->callController('PUT', 'calls', 'f04ea4691c59', '../calls', 'xml', 'call-list');

        $this->assertSame(404, $response->getStatusCode());
        Storage::disk('polycom_provisioning_uploads')->assertMissing('calls/f04ea4691c59-../calls.xml');
    }

    private function callController(
        string $method,
        string $bucket,
        string $id,
        string $kind,
        string $ext,
        string $body = '',
        ?Devices $device = null
    ) {
        $request = Request::create("/prov/{$bucket}/{$id}-{$kind}.{$ext}", $method, [], [], [], [], $body);
        $request->attributes->set('prov.device', $device ?? new Devices([
            'device_vendor' => 'polycom',
            'device_address' => 'f04ea4691c59',
        ]));

        return (new PolycomProvisioningFileController())->handle($request, $bucket, $id, $kind, $ext);
    }
}
