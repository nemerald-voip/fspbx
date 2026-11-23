<?php

namespace Tests\Unit;

use App\Jobs\SendTranscriptionEmail;
use App\Mail\CallTranscriptionReady;
use App\Mail\FaxSent;
use App\Mail\VoicemailEscalationCompletionMail;
use App\Models\VmNotifyLog;
use App\Models\VmNotifyNotification;
use Carbon\Carbon;
use Illuminate\Cache\Events\CacheHit;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DateTimeFormatHelpersTest extends TestCase
{
    private string $databasePath;
    private string $domain = '11111111-1111-1111-1111-111111111111';

    protected function setUp(): void
    {
        parent::setUp();

        $this->databasePath = sys_get_temp_dir() . '/fspbx-datetime-' . bin2hex(random_bytes(8)) . '.sqlite';
        touch($this->databasePath);
        config(['cache.default' => 'array', 'database.default' => 'sqlite', 'database.connections.sqlite.database' => $this->databasePath]);
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::create('v_default_settings', function (Blueprint $table) {
            $table->string('default_setting_uuid')->primary();
            $table->string('default_setting_category')->nullable();
            $table->string('default_setting_subcategory')->nullable();
            $table->string('default_setting_name')->nullable();
            $table->text('default_setting_value')->nullable();
            $table->string('default_setting_enabled')->nullable();
        });
        Schema::create('v_domain_settings', function (Blueprint $table) {
            $table->string('domain_setting_uuid')->primary();
            $table->string('domain_uuid')->nullable();
            $table->string('domain_setting_category')->nullable();
            $table->string('domain_setting_subcategory')->nullable();
            $table->string('domain_setting_name')->nullable();
            $table->text('domain_setting_value')->nullable();
            $table->string('domain_setting_enabled')->nullable();
        });
        Cache::flush();
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        @unlink($this->databasePath);
        parent::tearDown();
    }

    private function defaultSetting(string $subcategory, string $value): void
    {
        DB::table('v_default_settings')->insert([
            'default_setting_uuid' => bin2hex(random_bytes(16)), 'default_setting_category' => 'domain',
            'default_setting_subcategory' => $subcategory, 'default_setting_name' => 'text',
            'default_setting_value' => $value, 'default_setting_enabled' => 'true',
        ]);
    }

    private function domainSetting(string $subcategory, string $value): void
    {
        DB::table('v_domain_settings')->insert([
            'domain_setting_uuid' => bin2hex(random_bytes(16)), 'domain_uuid' => $this->domain,
            'domain_setting_category' => 'domain', 'domain_setting_subcategory' => $subcategory,
            'domain_setting_name' => 'text', 'domain_setting_value' => $value, 'domain_setting_enabled' => 'true',
        ]);
    }

    public function test_without_settings_the_existing_us_notation_is_kept(): void
    {
        $at = Carbon::create(2026, 1, 31, 15, 4, 5);

        $this->assertSame('3:04:05 PM Jan 31, 2026', $at->format(local_datetime_format($this->domain)));
        $this->assertSame('Jan 31, 2026', $at->format(local_date_format($this->domain)));
        $this->assertSame('January 31, 2026', $at->format(local_date_format($this->domain, true)));
        $this->assertSame('3:04 PM', $at->format(local_time_format($this->domain, false)));
        $this->assertSame('3 PM', $at->format(local_hour_format($this->domain)));
    }

    public function test_installation_default_applies_to_every_account(): void
    {
        $this->defaultSetting('date_format', 'eu');
        $this->defaultSetting('time_format', '24h');
        $at = Carbon::create(2026, 1, 31, 15, 4, 5);

        $this->assertSame('31/01/2026 15:04:05', $at->format(local_datetime_format($this->domain)));
        $this->assertSame('31 January 2026', $at->format(local_date_format($this->domain, true)));
        $this->assertSame('15:00', $at->format(local_hour_format($this->domain)));
    }

    public function test_account_setting_overrides_the_installation_default(): void
    {
        $this->defaultSetting('date_format', 'eu');
        $this->defaultSetting('time_format', '24h');
        $this->domainSetting('date_format', 'iso');
        $this->domainSetting('time_format', '12h');
        $at = Carbon::create(2026, 1, 31, 15, 4, 5);

        $this->assertSame('2026-01-31 3:04:05 PM', $at->format(local_datetime_format($this->domain)));
        $this->assertSame(['date_format' => 'iso', 'time_format' => '12h'], get_local_date_time_formats($this->domain));
    }

    public function test_unknown_values_fall_back_to_us_notation(): void
    {
        $this->defaultSetting('date_format', 'dd-mm-yyyy');
        $this->defaultSetting('time_format', 'military');

        $this->assertSame(['date_format' => 'us', 'time_format' => '12h'], get_local_date_time_formats($this->domain));
    }

    public function test_escalation_email_formats_message_and_logs_in_both_bodies(): void
    {
        $this->domainSetting('time_zone', 'Europe/Amsterdam');
        $this->domainSetting('date_format', 'eu');
        $this->domainSetting('time_format', '24h');

        $notification = new VmNotifyNotification([
            'domain_uuid' => $this->domain, 'status' => 'accepted', 'mailbox' => '1001',
            'message_left_at' => Carbon::parse('2026-01-31 23:50:00', 'UTC'),
        ]);
        $log = new VmNotifyLog(['level' => 'info', 'message' => 'Accepted']);
        $log->created_at = Carbon::parse('2026-01-31 23:51:00', 'UTC');
        $notification->setRelation('logs', collect([$log]));
        $notification->setRelation('attempts', collect());

        $content = (new VoicemailEscalationCompletionMail($notification))->content();
        foreach ([$content->htmlString, $content->with['renderedText']] as $body) {
            $this->assertStringContainsString('01/02/2026 00:50:00 CET', $body);
            $this->assertStringContainsString('01/02/2026 00:51:00 CET', $body);
        }
    }

    public function test_combined_format_reads_cached_preferences_once(): void
    {
        get_local_date_time_formats($this->domain);
        Event::fake([CacheHit::class]);
        local_datetime_format($this->domain);
        Event::assertDispatchedTimes(CacheHit::class, 1);
    }

    public function test_sent_fax_fallback_date_uses_the_fax_account(): void
    {
        $this->domainSetting('time_zone', 'Europe/Amsterdam');
        $this->domainSetting('date_format', 'eu');
        $this->domainSetting('time_format', '24h');
        $this->travelTo(Carbon::parse('2026-01-31 23:50:00', 'UTC'), function () {
            $content = (new FaxSent([
                'domain_uuid' => $this->domain, 'fax_destination' => '+12025550199', 'fax_pages' => 1,
            ]))->content();
            foreach ([$content->htmlString, $content->with['renderedText']] as $body) {
                $this->assertStringContainsString('01/02/2026 00:50', $body);
            }
        });
    }

    public function test_transcription_job_uses_its_record_account_without_a_session(): void
    {
        $this->domainSetting('time_zone', 'Europe/Amsterdam');
        $this->domainSetting('date_format', 'iso');
        $this->domainSetting('time_format', '24h');
        Schema::create('call_transcriptions', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('domain_uuid');
            $table->timestamps();
        });
        $uuid = '22222222-2222-4222-8222-222222222222';
        DB::table('call_transcriptions')->insert([
            'uuid' => $uuid, 'domain_uuid' => $this->domain, 'created_at' => '2026-01-31 23:50:00',
        ]);
        Mail::fake();
        $throttle = \Mockery::mock();
        $throttle->shouldReceive('allow', 'every')->andReturnSelf();
        $throttle->shouldReceive('then')->once()->andReturnUsing(fn ($send) => $send());
        Redis::shouldReceive('throttle')->with('transcriptions')->andReturn($throttle);

        (new SendTranscriptionEmail($uuid, 'operator@example.test'))->handle();

        Mail::assertSent(CallTranscriptionReady::class, fn ($mail) =>
            $mail->attributes['domain_uuid'] === $this->domain
            && $mail->attributes['date'] === '2026-02-01 @ 00:50'
        );
    }
}
