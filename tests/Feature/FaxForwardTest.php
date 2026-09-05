<?php

namespace Tests\Feature;

use App\Jobs\ProcessFaxWebhookEventJob;
use App\Jobs\SendFaxJob;
use App\Models\Faxes;
use App\Models\Domain;
use App\Models\OutboundFax;
use App\Services\FaxForwardService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use ReflectionMethod;
use Tests\TestCase;

class FaxForwardTest extends TestCase
{
    private string $directory;
    private Faxes $fax;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir() . '/fax-forward-' . Str::uuid();
        mkdir($this->directory);
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array',
            'logging.default' => 'null',
            'filesystems.disks.fax' => ['driver' => 'local', 'root' => $this->directory],
        ]);
        DB::purge('sqlite');
        Bus::fake();

        $this->table('v_fax', 'fax_uuid', [
            'domain_uuid', 'fax_forward_number', 'fax_extension', 'fax_destination_number',
            'fax_caller_id_number', 'fax_caller_id_name', 'fax_email', 'fax_prefix', 'accountcode',
        ]);
        $this->table('v_extensions', 'extension_uuid', ['domain_uuid', 'extension']);
        $this->table('v_domain_settings', 'domain_setting_uuid', [
            'domain_uuid', 'domain_setting_subcategory', 'domain_setting_enabled', 'domain_setting_value',
        ]);
        $this->table('v_default_settings', 'default_setting_uuid', [
            'default_setting_subcategory', 'default_setting_enabled', 'default_setting_value',
        ]);
        $this->table('v_fax_logs', 'fax_log_uuid', [
            'domain_uuid', 'fax_uuid', 'source', 'destination', 'fax_success', 'fax_result_code',
            'fax_result_text', 'fax_file', 'fax_ecm_used', 'fax_local_station_id',
            'fax_document_transferred_pages', 'fax_document_total_pages', 'fax_image_resolution',
            'fax_image_size', 'fax_bad_rows', 'fax_transfer_rate', 'fax_uri', 'fax_duration',
            'fax_date', 'fax_epoch',
        ]);
        $this->table('v_fax_files', 'fax_file_uuid', [
            'fax_uuid', 'fax_mode', 'fax_destination', 'fax_file_type', 'fax_file_path',
            'fax_caller_id_name', 'fax_caller_id_number', 'fax_date', 'fax_epoch', 'domain_uuid',
        ]);
        $this->table('outbound_faxes', 'outbound_fax_uuid', [
            'domain_uuid', 'fax_uuid', 'status', 'source', 'source_name', 'destination',
            'email', 'subject', 'body', 'file_path', 'total_pages', 'prefix', 'accountcode',
            'retry_count', 'retry_limit', 'retry_at', 'created_at', 'updated_at',
        ]);

        $this->fax = Faxes::create([
            'domain_uuid' => (string) Str::uuid(),
            'fax_forward_number' => '201', 'fax_extension' => '50000',
            'fax_destination_number' => '50000', 'accountcode' => 'account.test',
            'fax_caller_id_number' => '+12025550100', 'fax_caller_id_name' => 'Fax Desk',
            'fax_prefix' => '9',
        ]);
        file_put_contents($this->directory . '/received.tif', 'received fax fixture');
        file_put_contents($this->directory . '/received.pdf', 'received PDF fixture');
    }

    protected function tearDown(): void
    {
        DB::purge('sqlite');
        (new Filesystem)->deleteDirectory($this->directory);
        parent::tearDown();
    }

    public function test_received_fax_queues_one_forward_and_keeps_its_own_attachment(): void
    {
        $job = $this->receivedJob();
        $job->handle();
        $job->handle();
        $this->assertSame(1, OutboundFax::count());
        $outbound = OutboundFax::first();
        $this->assertSame('201', $outbound->destination);
        $this->assertSame($this->fax->domain_uuid, $outbound->domain_uuid);
        $this->assertSame($this->fax->fax_uuid, $outbound->fax_uuid);
        $this->assertSame('+12025550100', $outbound->source);
        $this->assertSame('waiting', $outbound->status);
        $this->assertSame(2, $outbound->total_pages);
        $this->assertSame(5, $outbound->retry_limit);
        unlink($this->directory . '/received.tif');
        $this->assertSame('received fax fixture', file_get_contents($outbound->file_path));
        $this->assertFileExists(substr($outbound->file_path, 0, -4) . '.pdf');
        Bus::assertDispatchedTimes(SendFaxJob::class, 1);
        Bus::assertDispatched(SendFaxJob::class, fn ($job) => $job->outboundFaxUuid === $outbound->getKey());
    }

    public function test_failed_outbound_blank_and_wrong_tenant_events_do_not_forward(): void
    {
        $this->receivedJob(['fax_success' => '0'])->handle();
        $this->receivedJob(['call_direction' => 'outbound'], 'fax.sent')->handle();
        $this->receivedJob(['call_direction' => 'outbound'])->handle();
        $this->receivedJob(['domain_uuid' => (string) Str::uuid()])->handle();
        $this->fax->update(['fax_forward_number' => '  ']);
        $this->receivedJob()->handle();
        $this->assertSame(0, OutboundFax::count());
        Bus::assertNotDispatched(SendFaxJob::class);
    }

    public function test_external_forward_uses_account_country_and_prefix(): void
    {
        $this->fax->update(['fax_forward_number' => '020 7946 0958']);
        DB::table('v_domain_settings')->insert([
            'domain_setting_uuid' => (string) Str::uuid(), 'domain_uuid' => $this->fax->domain_uuid,
            'domain_setting_subcategory' => 'country', 'domain_setting_enabled' => 'true',
            'domain_setting_value' => 'GB',
        ]);
        $this->receivedJob()->handle();
        $outbound = OutboundFax::first();
        $this->assertSame('+442079460958', $outbound->destination);
        $this->assertSame('9', $outbound->prefix);
    }

    public function test_missing_file_can_be_retried_without_losing_the_forward(): void
    {
        $uuid = (string) Str::uuid();
        $service = app(FaxForwardService::class);
        try {
            $service->forward($this->fax, $uuid, $this->directory . '/missing.tif', 2);
            $this->fail('Missing attachment must fail for retry.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Received fax file is unavailable for forwarding.', $e->getMessage());
        }
        $this->assertSame(0, OutboundFax::count());
        $service->forward($this->fax, $uuid, $this->directory . '/received.tif', 2);
        $this->assertSame(1, OutboundFax::count());
    }

    public function test_local_forward_keeps_fax_variables_and_retry_overrides_without_a_trunk_prefix(): void
    {
        DB::table('v_extensions')->insert([
            'extension_uuid' => (string) Str::uuid(),
            'domain_uuid' => $this->fax->domain_uuid, 'extension' => '201',
        ]);
        $this->receivedJob()->handle();
        $outbound = OutboundFax::first();
        $this->fax->setRelation('domain', new Domain(['domain_name' => 'account.test']));
        $outbound->setRelation('faxServer', $this->fax);
        cache()->put('fax_dialplan_variables', [
            'fax_enable_t38=true', 'fax_enable_t38_request=false', 'fax_use_ecm=true',
            'fax_disable_v17=true', 'fax_verbose=false',
        ], 60);
        $job = new SendFaxJob($outbound->getKey());
        $method = new ReflectionMethod(SendFaxJob::class, 'buildDialString');
        $outbound->retry_count = 1;
        $command = $method->invoke($job, $outbound, (string) Str::uuid(), (string) Str::uuid());
        $this->assertStringContainsString('}user/201@account.test ', $command);
        $this->assertStringContainsString("'txfax:{$outbound->file_path}'", $command);
        $this->assertStringContainsString("absolute_codec_string='PCMU,PCMA'", $command);
        $this->assertStringContainsString("fax_ident='+12025550100'", $command);
        $this->assertStringContainsString('fax_enable_t38_request=false', $command);
        $this->assertStringContainsString('fax_use_ecm=true', $command);
        $this->assertStringContainsString("api_hangup_hook='lua lua/fax_hangup.lua'", $command);
        $this->assertStringContainsString('outbound_fax_uuid=' . $outbound->getKey(), $command);
        $this->assertStringNotContainsString('loopback/', $command);

        $outbound->retry_count = 2;
        $retry = $method->invoke($job, $outbound, (string) Str::uuid(), (string) Str::uuid());
        $this->assertStringContainsString('fax_use_ecm=false', $retry);
        $this->assertStringContainsString('fax_enable_t38_request=true', $retry);
        $this->assertStringNotContainsString('fax_use_ecm=true', $retry);
        $this->assertStringContainsString('fax_disable_v17=true', $retry);
    }

    public function test_extension_in_another_account_does_not_select_local_delivery(): void
    {
        DB::table('v_extensions')->insert([
            'extension_uuid' => (string) Str::uuid(),
            'domain_uuid' => (string) Str::uuid(), 'extension' => '201',
        ]);
        $this->receivedJob()->handle();
        $outbound = OutboundFax::first();
        $method = new ReflectionMethod(SendFaxJob::class, 'localFaxEndpoint');
        $this->assertNull($method->invoke(new SendFaxJob($outbound->getKey()), $outbound, 'account.test'));
    }

    private function receivedJob(array $data = [], string $event = 'fax.received'): ProcessFaxWebhookEventJob
    {
        return new ProcessFaxWebhookEventJob($event, (string) time(), array_merge([
            'uuid' => (string) Str::uuid(), 'domain_uuid' => $this->fax->domain_uuid,
            'fax_uuid' => $this->fax->fax_uuid, 'fax_file' => $this->directory . '/received.tif',
            'fax_success' => '1', 'call_direction' => 'inbound', 'fax_document_transferred_pages' => '2',
        ], $data));
    }

    private function table(string $name, string $key, array $columns): void
    {
        Schema::create($name, function (Blueprint $table) use ($key, $columns) {
            $table->string($key)->primary();
            foreach ($columns as $column) {
                $table->string($column)->nullable();
            }
        });
    }
}
