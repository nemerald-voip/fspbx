<?php

namespace Tests\Unit;

use App\Exceptions\Handler;
use App\Services\EmailDeliveryFailureReporter;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Events\Dispatcher;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\QueueManager;
use Illuminate\Queue\Worker;
use Illuminate\Queue\WorkerOptions;
use Mockery;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionMethod;
use RuntimeException;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\HttpClient\Response\MockResponse;

class EmailDeliveryFailureReporterTest extends TestCase
{
    private Container $container;
    private Handler $handler;
    private EmailDeliveryFailureReporter $reporter;
    private $stream;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stream = fopen('php://memory', 'w+');
        $streamHandler = new StreamHandler($this->stream);
        $formatter = new LineFormatter(null, null, true, true);
        $formatter->includeStacktraces(true);
        $streamHandler->setFormatter($formatter);
        $logger = new Logger('test', [$streamHandler]);

        $this->container = new Container;
        $this->container->instance(LoggerInterface::class, $logger);
        $this->reporter = new EmailDeliveryFailureReporter($logger, new Repository([
            'mail' => ['default' => 'postmark'],
        ]));
        $this->container->instance(EmailDeliveryFailureReporter::class, $this->reporter);
        $this->handler = new Handler($this->container);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        fclose($this->stream);

        parent::tearDown();
    }

    public function test_job_and_framework_reporting_produce_one_line_with_job_context(): void
    {
        $exception = new TransportException('All transports failed.');
        $exception->appendDebug('Sensitive provider debug that must not appear in the application log.');

        $this->reporter->report($exception, [
            'job' => 'SendNewVoicemailNotificationByEmail',
            'uuid' => 'voicemail-message-id',
            'attempt' => 3,
            'max_tries' => 10,
        ]);
        $this->handler->report($exception);

        $output = $this->output();
        $this->assertSame(1, substr_count($output, "\n"));
        $this->assertStringContainsString('MAIL_ALL_TRANSPORTS_FAILED', $output);
        $this->assertStringContainsString('voicemail-message-id', $output);
        $this->assertStringContainsString('"attempt":3', $output);
        $this->assertStringNotContainsString('stacktrace', $output);
        $this->assertStringNotContainsString('Sensitive provider debug', $output);
    }

    public function test_postmark_suppression_includes_the_provider_code_without_a_stack_trace(): void
    {
        $exception = new HttpTransportException(
            "Unable to send an email:\r\n Found inactive recipients. (code 406).",
            new MockResponse('', ['http_code' => 422]),
        );

        $this->handler->report($exception);

        $output = $this->output();
        $this->assertSame(1, substr_count($output, "\n"));
        $this->assertStringContainsString('MAIL_RECIPIENT_SUPPRESSED', $output);
        $this->assertStringContainsString('"provider_code":406', $output);
        $this->assertStringContainsString('"mailer":"postmark"', $output);
        $this->assertStringNotContainsString('stacktrace', $output);
    }

    /** @dataProvider transportErrors */
    public function test_unhandled_transport_errors_have_stable_codes(string $message, string $code): void
    {
        $this->handler->report(new TransportException($message));

        $this->assertStringContainsString('"code":"'.$code.'"', $this->output());
    }

    public static function transportErrors(): array
    {
        return [
            'cooldown' => ['No transports found.', 'MAIL_NO_TRANSPORT_AVAILABLE'],
            'authentication' => ['Failed to authenticate on SMTP server.', 'MAIL_AUTH_FAILED'],
            'postmark token' => ['Unable to send an email: Bad API token. (code 10).', 'MAIL_AUTH_FAILED'],
            'connection' => ['Connection timed out.', 'MAIL_TRANSPORT_ERROR'],
        ];
    }

    public function test_separate_attempts_are_logged_and_long_errors_stay_bounded(): void
    {
        $message = str_repeat("Provider error\n", 1000);
        $this->handler->report(new TransportException($message));
        $this->handler->report(new TransportException($message));

        $output = $this->output();
        $this->assertSame(2, substr_count($output, "\n"));
        $this->assertLessThan(1500, strlen($output));
    }

    public function test_unexpected_errors_keep_normal_exception_reporting(): void
    {
        $this->handler->report(new RuntimeException('Unexpected template failure.'));

        $output = $this->output();
        $this->assertStringContainsString('Unexpected template failure.', $output);
        $this->assertStringContainsString('stacktrace', $output);
        $this->assertStringNotContainsString('Email delivery failed', $output);
    }

    /** @dataProvider queueAttempts */
    public function test_queue_retry_and_final_failure_keep_the_original_exception(int $attempt, bool $exhausted): void
    {
        $exception = new TransportException('Connection timed out.');
        $failed = false;
        $job = Mockery::mock(Job::class);
        $job->shouldReceive('maxTries')->andReturn(3);
        $job->shouldReceive('retryUntil')->andReturn(null);
        $job->shouldReceive('attempts')->andReturn($attempt);
        $job->shouldReceive('isDeleted', 'isReleased')->andReturn(false);
        $job->shouldReceive('hasFailed')->andReturnUsing(function () use (&$failed) {
            return $failed;
        });
        $job->shouldReceive('uuid')->andReturn('test-job');
        $job->shouldReceive('maxExceptions')->andReturn(null);
        $job->shouldReceive('backoff')->andReturn(30);
        $job->shouldReceive('fire')->once()->andReturnUsing(function () use ($exception, $attempt) {
            $this->reporter->report($exception, ['job' => 'TestMailJob', 'attempt' => $attempt]);
            throw $exception;
        });

        if ($exhausted) {
            $job->shouldReceive('fail')->once()->with($exception)->andReturnUsing(function () use (&$failed) {
                $failed = true;
            });
            $job->shouldNotReceive('release');
        } else {
            $job->shouldReceive('release')->once()->with(30);
            $job->shouldNotReceive('fail');
        }

        $events = new Dispatcher($this->container);
        $observed = null;
        $events->listen(JobExceptionOccurred::class, function ($event) use (&$observed) {
            $observed = $event->exception;
        });
        $worker = new Worker(new QueueManager($this->container), $events, $this->handler, fn () => false);
        $run = new ReflectionMethod(Worker::class, 'runJob');
        $run->setAccessible(true);
        $run->invoke($worker, $job, 'test', new WorkerOptions(backoff: 30, maxTries: 3));

        $this->assertSame($exception, $observed);
        $this->assertSame($exhausted, $failed);
        $this->assertSame(1, substr_count($this->output(), "\n"));
    }

    public static function queueAttempts(): array
    {
        return [
            'retry' => [1, false],
            'exhausted' => [3, true],
        ];
    }

    private function output(): string
    {
        rewind($this->stream);

        return stream_get_contents($this->stream);
    }
}
