<?php

namespace Tests\Feature;

use App\Jobs\CheckFaxServiceStatus;
use App\Jobs\SendFaxQueueThresholdExceededNotification;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use ReflectionProperty;
use Tests\TestCase;

class CheckFaxServiceStatusTest extends TestCase
{
    private string $databasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->databasePath = sys_get_temp_dir() . '/fspbx-check-fax-service-' . bin2hex(random_bytes(8)) . '.sqlite';
        touch($this->databasePath);

        config([
            'cache.default' => 'array',
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $this->databasePath,
            'logging.default' => 'null',
        ]);

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $this->createSchema();
        $this->insertSettings();

        Queue::fake();

        $throttle = \Mockery::mock();
        $throttle->shouldReceive('allow')->with(1)->andReturnSelf();
        $throttle->shouldReceive('every')->with(30)->andReturnSelf();
        $throttle->shouldReceive('then')->once()->andReturnUsing(function ($callback) {
            return $callback();
        });

        Redis::shouldReceive('throttle')->once()->with('default')->andReturn($throttle);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        DB::purge('sqlite');

        if (isset($this->databasePath) && is_file($this->databasePath)) {
            unlink($this->databasePath);
        }

        parent::tearDown();
    }

    public function test_in_progress_faxes_do_not_dilute_the_recent_failure_rate(): void
    {
        Carbon::setTestNow('2026-07-29 00:30:00');

        foreach (range(1, 5) as $minutesAgo) {
            $this->insertFax('failed', now()->subHours(2), now()->subMinutes($minutesAgo));
        }

        foreach (range(1, 4) as $minutesAgo) {
            $this->insertFax('sending', now()->subMinutes($minutesAgo), now()->subMinutes($minutesAgo));
        }

        (new CheckFaxServiceStatus())->handle();

        Queue::assertPushed(SendFaxQueueThresholdExceededNotification::class, function ($job) {
            $params = $this->notificationParams($job);

            return $params['failedFaxes'] === 5
                && $params['totalChecked'] === 5
                && (float) $params['failureRate'] === 100.0;
        });
        Queue::assertPushed(SendFaxQueueThresholdExceededNotification::class, 1);
    }

    public function test_sending_faxes_are_aged_from_creation_even_after_recent_updates(): void
    {
        Carbon::setTestNow('2026-07-29 00:30:00');

        foreach (range(1, 5) as $minutesAgo) {
            $this->insertFax('sending', now()->subHours(2), now()->subMinutes($minutesAgo));
        }

        (new CheckFaxServiceStatus())->handle();

        Queue::assertPushed(SendFaxQueueThresholdExceededNotification::class, function ($job) {
            $params = $this->notificationParams($job);

            return $params['pendingFaxes'] === 5
                && $params['waitTimeThreshold'] === '60';
        });
        Queue::assertPushed(SendFaxQueueThresholdExceededNotification::class, 1);
    }

    private function notificationParams(SendFaxQueueThresholdExceededNotification $job): array
    {
        $property = new ReflectionProperty($job, 'params');
        $property->setAccessible(true);

        return $property->getValue($job);
    }

    private function insertFax(string $status, Carbon $createdAt, Carbon $updatedAt): void
    {
        DB::table('outbound_faxes')->insert([
            'outbound_fax_uuid' => (string) Str::uuid(),
            'domain_uuid' => (string) Str::uuid(),
            'fax_uuid' => (string) Str::uuid(),
            'status' => $status,
            'destination' => '+15551234567',
            'file_path' => sys_get_temp_dir() . '/outbound-fax.tif',
            'retry_count' => 0,
            'retry_limit' => 5,
            'created_at' => $createdAt,
            'updated_at' => $updatedAt,
        ]);
    }

    private function insertSettings(): void
    {
        foreach ([
            'fax_service_threshold' => '5',
            'fax_wait_time_threshold' => '60',
            'fax_service_notify_email' => 'admin@example.test',
        ] as $subcategory => $value) {
            DB::table('v_default_settings')->insert([
                'default_setting_uuid' => (string) Str::uuid(),
                'default_setting_category' => 'scheduled_jobs',
                'default_setting_subcategory' => $subcategory,
                'default_setting_name' => 'text',
                'default_setting_value' => $value,
                'default_setting_enabled' => 'true',
            ]);
        }
    }

    private function createSchema(): void
    {
        Schema::create('outbound_faxes', function (Blueprint $table) {
            $table->string('outbound_fax_uuid')->primary();
            $table->string('domain_uuid');
            $table->string('fax_uuid');
            $table->string('status', 16)->default('waiting');
            $table->string('destination', 64);
            $table->text('file_path');
            $table->unsignedSmallInteger('retry_count')->default(0);
            $table->unsignedSmallInteger('retry_limit')->default(5);
            $table->timestamps();
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
