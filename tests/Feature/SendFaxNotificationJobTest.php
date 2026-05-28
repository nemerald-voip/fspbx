<?php

namespace Tests\Feature;

use App\Jobs\SendFaxNotificationJob;
use App\Mail\FaxSent;
use App\Models\OutboundFax;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use ReflectionMethod;
use Tests\TestCase;

class SendFaxNotificationJobTest extends TestCase
{
    private string $databasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->databasePath = sys_get_temp_dir() . '/fspbx-fax-notification-' . bin2hex(random_bytes(8)) . '.sqlite';
        touch($this->databasePath);

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $this->databasePath,
            'mail.from.address' => 'noreply@example.test',
            'mail.from.name' => 'FS PBX',
        ]);

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $this->createSchema();
    }

    protected function tearDown(): void
    {
        DB::purge('sqlite');

        if (isset($this->databasePath) && is_file($this->databasePath)) {
            unlink($this->databasePath);
        }

        parent::tearDown();
    }

    public function test_it_uses_the_current_successful_attempt_pages_for_sent_notifications(): void
    {
        $outboundFaxUuid = (string) Str::uuid();
        $failedAttemptUuid = (string) Str::uuid();
        $successfulAttemptUuid = (string) Str::uuid();

        $this->insertOutboundFax($outboundFaxUuid, [
            'status' => 'sent',
            'current_attempt_uuid' => $successfulAttemptUuid,
        ]);

        $this->insertFaxLog($outboundFaxUuid, $failedAttemptUuid, [
            'fax_success' => '0',
            'fax_document_transferred_pages' => 8,
            'fax_document_total_pages' => 25,
            'fax_date' => Carbon::parse('2026-05-28 10:00:00', 'UTC'),
        ]);

        $this->insertFaxLog($outboundFaxUuid, $successfulAttemptUuid, [
            'fax_success' => '1',
            'fax_document_transferred_pages' => 25,
            'fax_document_total_pages' => 25,
            'fax_date' => Carbon::parse('2026-05-28 10:20:00', 'UTC'),
        ]);

        $attributes = $this->buildAttributes($outboundFaxUuid);

        $this->assertSame('25', $attributes['fax_pages']);
        $this->assertSame('25', $attributes['fax_total_pages']);

        Mail::fake();
        Mail::to('sender@example.test')->send(new FaxSent($attributes));

        Mail::assertSent(FaxSent::class, function (FaxSent $mail) {
            $body = view('emails.fax.success-text', ['attributes' => $mail->attributes])->render();

            $this->assertSame('Fax sent to +15551234567 (25 pages)', $mail->attributes['email_subject']);
            $this->assertStringContainsString('Pages sent: 25.', $body);
            $this->assertStringNotContainsString('8 of 25', $body);

            return true;
        });
    }

    public function test_it_falls_back_to_the_newest_log_when_current_attempt_log_is_missing(): void
    {
        $outboundFaxUuid = (string) Str::uuid();

        $this->insertOutboundFax($outboundFaxUuid, [
            'status' => 'sent',
            'current_attempt_uuid' => (string) Str::uuid(),
        ]);

        $this->insertFaxLog($outboundFaxUuid, (string) Str::uuid(), [
            'fax_success' => '0',
            'fax_document_transferred_pages' => 8,
            'fax_document_total_pages' => 25,
            'fax_date' => Carbon::parse('2026-05-28 10:00:00', 'UTC'),
        ]);

        $this->insertFaxLog($outboundFaxUuid, (string) Str::uuid(), [
            'fax_success' => '1',
            'fax_document_transferred_pages' => 25,
            'fax_document_total_pages' => 25,
            'fax_date' => Carbon::parse('2026-05-28 10:20:00', 'UTC'),
        ]);

        $attributes = $this->buildAttributes($outboundFaxUuid);

        $this->assertSame('25', $attributes['fax_pages']);
        $this->assertSame('25', $attributes['fax_total_pages']);
    }

    private function buildAttributes(string $outboundFaxUuid): array
    {
        $fax = OutboundFax::findOrFail($outboundFaxUuid);
        $job = new SendFaxNotificationJob($outboundFaxUuid);
        $method = new ReflectionMethod(SendFaxNotificationJob::class, 'buildAttributes');
        $method->setAccessible(true);

        return $method->invoke($job, $fax);
    }

    private function insertOutboundFax(string $outboundFaxUuid, array $overrides = []): void
    {
        DB::table('outbound_faxes')->insert(array_merge([
            'outbound_fax_uuid' => $outboundFaxUuid,
            'domain_uuid' => (string) Str::uuid(),
            'fax_uuid' => (string) Str::uuid(),
            'status' => 'waiting',
            'source' => '+15557654321',
            'destination' => '+15551234567',
            'email' => 'sender@example.test',
            'file_path' => sys_get_temp_dir() . '/outbound-fax.tif',
            'total_pages' => 25,
            'retry_count' => 0,
            'retry_limit' => 5,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    private function insertFaxLog(string $outboundFaxUuid, string $attemptUuid, array $overrides = []): void
    {
        $faxDate = $overrides['fax_date'] ?? now();
        unset($overrides['fax_date']);

        DB::table('v_fax_logs')->insert(array_merge([
            'fax_log_uuid' => (string) Str::uuid(),
            'outbound_fax_uuid' => $outboundFaxUuid,
            'outbound_fax_attempt_uuid' => $attemptUuid,
            'fax_success' => '0',
            'fax_document_transferred_pages' => null,
            'fax_document_total_pages' => null,
            'fax_duration' => 60,
            'fax_date' => $faxDate,
            'fax_epoch' => $faxDate instanceof Carbon ? $faxDate->timestamp : Carbon::parse($faxDate)->timestamp,
        ], $overrides));
    }

    private function createSchema(): void
    {
        Schema::create('outbound_faxes', function (Blueprint $table) {
            $table->string('outbound_fax_uuid')->primary();
            $table->string('domain_uuid');
            $table->string('fax_uuid');
            $table->string('status', 16)->default('waiting');
            $table->string('source', 64)->nullable();
            $table->string('source_name', 128)->nullable();
            $table->string('destination', 64);
            $table->string('destination_name', 128)->nullable();
            $table->string('email')->nullable();
            $table->string('subject')->nullable();
            $table->text('body')->nullable();
            $table->text('file_path');
            $table->unsignedSmallInteger('total_pages')->nullable();
            $table->string('prefix', 16)->nullable();
            $table->string('accountcode', 64)->nullable();
            $table->unsignedSmallInteger('retry_count')->default(0);
            $table->unsignedSmallInteger('retry_limit')->default(5);
            $table->timestamp('retry_at')->nullable();
            $table->text('command')->nullable();
            $table->text('response')->nullable();
            $table->string('call_uuid')->nullable();
            $table->string('current_attempt_uuid')->nullable();
            $table->timestamp('notify_sent_at')->nullable();
            $table->timestamps();
        });

        Schema::create('v_fax_logs', function (Blueprint $table) {
            $table->string('fax_log_uuid')->primary();
            $table->string('domain_uuid')->nullable();
            $table->string('fax_uuid')->nullable();
            $table->string('outbound_fax_uuid')->nullable();
            $table->string('outbound_fax_attempt_uuid')->nullable();
            $table->string('source', 64)->nullable();
            $table->string('destination', 64)->nullable();
            $table->string('fax_success')->nullable();
            $table->integer('fax_result_code')->nullable();
            $table->text('fax_result_text')->nullable();
            $table->text('fax_file')->nullable();
            $table->integer('fax_document_transferred_pages')->nullable();
            $table->integer('fax_document_total_pages')->nullable();
            $table->integer('fax_duration')->nullable();
            $table->timestamp('fax_date')->nullable();
            $table->integer('fax_epoch')->nullable();
        });

        Schema::create('v_default_settings', function (Blueprint $table) {
            $table->string('default_setting_uuid')->primary();
            $table->string('default_setting_category')->nullable();
            $table->string('default_setting_subcategory')->nullable();
            $table->string('default_setting_name')->nullable();
            $table->text('default_setting_value')->nullable();
            $table->string('default_setting_enabled')->nullable();
        });
    }
}
