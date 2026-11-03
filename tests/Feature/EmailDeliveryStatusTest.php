<?php

namespace Tests\Feature;

use App\Jobs\SendAppCredentials;
use App\Jobs\SendFaxInTransitNotification;
use App\Mail\AppCredentials;
use App\Mail\BaseMailable;
use App\Mail\FaxInTransit;
use App\Models\EmailLog;
use App\Services\EmailTemplateService;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Mockery;
use RuntimeException;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Transport\FailoverTransport;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Tests\TestCase;
use Throwable;

class EmailDeliveryStatusTest extends TestCase
{
    private string $originalConnection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalConnection = config('database.default');
        config([
            'database.default' => 'email_delivery_status_test',
            'database.connections.email_delivery_status_test' => [
                'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
            ],
            'mail.default' => 'array',
            'mail.mailers.array' => ['transport' => 'array'],
            'mail.from.address' => 'noreply@example.test',
            'logging.default' => 'null',
        ]);
        DB::purge('email_delivery_status_test');
        Mail::purge('array');

        Schema::create('v_default_settings', function (Blueprint $table) {
            $table->string('default_setting_category');
            $table->string('default_setting_subcategory');
            $table->string('default_setting_value')->nullable();
            $table->string('default_setting_enabled')->default('true');
        });
        Schema::create('v_domain_settings', function (Blueprint $table) {
            $table->uuid('domain_uuid');
            $table->string('domain_setting_subcategory');
            $table->string('domain_setting_value')->nullable();
            $table->string('domain_setting_enabled')->default('true');
        });
        Schema::create('email_log', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('domain_uuid')->nullable();
            foreach (['from', 'to', 'cc', 'bcc', 'subject', 'status', 'provider', 'provider_message_stream', 'provider_message_id'] as $column) {
                $table->string($column)->nullable();
            }
            foreach (['html_body', 'text_body', 'sent_debug_info'] as $column) {
                $table->text($column)->nullable();
            }
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        DB::purge('email_delivery_status_test');
        config(['database.default' => $this->originalConnection]);
        parent::tearDown();
    }

    public function test_transport_failure_marks_the_matching_email_failed_and_preserves_the_exception(): void
    {
        Mail::to('earlier@example.test')->send($this->message());
        $earlier = EmailLog::query()->sole();
        $exception = new TransportException('All transports failed.');
        $exception->appendDebug('Private provider debug and credentials');
        Mail::mailer()->setSymfonyTransport($this->failingTransport($exception));

        $this->sendExpectingFailure($this->message(), $exception);

        $failed = EmailLog::query()->where('to', 'recipient@example.test')->sole();
        $this->assertSame('failed', $failed->status);
        $this->assertStringContainsString('All transports failed.', $failed->sent_debug_info);
        $this->assertStringNotContainsString('Private provider debug', $failed->sent_debug_info);
        $this->assertSame('sent', $earlier->refresh()->status);
    }

    public function test_successful_retry_reuses_the_log_and_changes_failed_to_sent(): void
    {
        $id = (string) Str::uuid();
        $exception = new TransportException('No transports found.');
        Mail::mailer()->setSymfonyTransport($this->failingTransport($exception));
        $this->sendExpectingFailure($this->message(['logId' => $id]), $exception);
        $this->assertSame('failed', EmailLog::findOrFail($id)->status);

        Mail::mailer()->setSymfonyTransport(new ArrayTransport());
        Mail::to('recipient@example.test')->send($this->message(['logId' => $id]));

        $this->assertSame(1, EmailLog::count());
        $this->assertSame('sent', EmailLog::findOrFail($id)->status);
        $this->assertStringContainsString('No transports found.', EmailLog::findOrFail($id)->sent_debug_info);
    }

    public function test_failover_success_is_sent_even_when_the_first_transport_fails(): void
    {
        Mail::mailer()->setSymfonyTransport(new FailoverTransport([
            $this->failingTransport(new TransportException('Provider rejected the message.')),
            new ArrayTransport(),
        ]));

        Mail::to('recipient@example.test')->send($this->message());

        $this->assertSame('sent', EmailLog::query()->sole()->status);
        $this->assertNull(EmailLog::query()->sole()->sent_debug_info);
    }

    public function test_non_transport_failure_after_logging_does_not_leave_sending(): void
    {
        $exception = new RuntimeException('Could not read the attachment.');
        Mail::mailer()->setSymfonyTransport($this->failingTransport($exception));

        $this->sendExpectingFailure($this->message(), $exception);

        $this->assertSame('failed', EmailLog::query()->sole()->status);
    }

    public function test_provider_permanent_failure_is_not_overwritten(): void
    {
        $id = (string) Str::uuid();
        $exception = new TransportException('Provider rejected the message.');
        $transport = Mockery::mock(TransportInterface::class);
        $transport->shouldReceive('send')->once()->andReturnUsing(function () use ($id, $exception) {
            EmailLog::whereKey($id)->update(['status' => 'permanent_failed', 'sent_debug_info' => 'Provider bounce']);
            throw $exception;
        });
        Mail::mailer()->setSymfonyTransport($transport);

        $this->sendExpectingFailure($this->message(['logId' => $id]), $exception);

        $this->assertSame('permanent_failed', EmailLog::findOrFail($id)->status);
        $this->assertSame('Provider bounce', EmailLog::findOrFail($id)->sent_debug_info);
    }

    /** @dataProvider retriedJobs */
    public function test_rehydrated_job_attempts_keep_the_same_email_log_id(string $jobClass, string $mailClass, string $throttle): void
    {
        Mail::fake();
        $this->mock(EmailTemplateService::class)->shouldReceive('render')->andReturn(['available' => false]);
        $limiter = Mockery::mock();
        Redis::shouldReceive('throttle')->with($throttle)->andReturn($limiter);
        $limiter->shouldReceive('allow')->with(2)->andReturnSelf();
        $limiter->shouldReceive('every')->with(1)->andReturnSelf();
        $limiter->shouldReceive('then')->twice()->andReturnUsing(fn ($callback) => $callback());
        $queueJob = Mockery::mock(Job::class);
        $id = (string) Str::uuid();
        $queueJob->shouldReceive('uuid')->andReturn($id);
        $payload = serialize(new $jobClass([
            'email' => 'recipient@example.test', 'from' => 'recipient@example.test',
            'domain' => 'example.test', 'username' => '100', 'password' => 'test-only',
            'fax_destination' => '+12025550123',
        ]));

        // Queue retries deserialize the original payload; mutations from the first run are lost.
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $job = unserialize($payload);
            $job->setJob($queueJob);
            $job->handle();
        }

        Mail::assertSent($mailClass, 2);
        foreach (Mail::sent($mailClass) as $mail) {
            $this->assertSame($id, $mail->attributes['logId']);
        }
    }

    public static function retriedJobs(): array
    {
        return [
            'app credentials' => [SendAppCredentials::class, AppCredentials::class, 'emails'],
            'fax progress' => [SendFaxInTransitNotification::class, FaxInTransit::class, 'fax'],
        ];
    }

    private function message(array $attributes = []): BaseMailable
    {
        return new class($attributes + ['email_subject' => 'Credentials notification']) extends BaseMailable
        {
            public function content(): Content
            {
                return new Content(htmlString: '<p>Notification</p>');
            }
        };
    }

    private function failingTransport(Throwable $exception): TransportInterface
    {
        $transport = Mockery::mock(TransportInterface::class);
        $transport->shouldReceive('send')->once()->andThrow($exception);
        $transport->shouldReceive('__toString')->andReturn('test');

        return $transport;
    }

    private function sendExpectingFailure(BaseMailable $message, Throwable $exception): void
    {
        try {
            Mail::to('recipient@example.test')->send($message);
            $this->fail('Expected delivery to fail.');
        } catch (Throwable $caught) {
            $this->assertSame($exception, $caught);
        }
    }
}
