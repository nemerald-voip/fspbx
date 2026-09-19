<?php

namespace Tests\Unit;

use App\Services\FreeswitchEslService;
use Illuminate\Container\Container;
use Mockery;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class FreeswitchEslSubscriptionTest extends TestCase
{
    private Container $previousContainer;

    protected function setUp(): void
    {
        $this->previousContainer = Container::getInstance();
        $container = new Container();
        $container->instance('log', new NullLogger());
        Container::setInstance($container);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        Container::setInstance($this->previousContainer);
        parent::tearDown();
    }

    private function service($connection): FreeswitchEslService
    {
        return new class($connection) extends FreeswitchEslService {
            public function __construct($connection) { $this->conn = $connection; }
        };
    }

    private function reply(?string $text): ?object
    {
        return $text === null ? null : new class($text) {
            public function __construct(private string $text) {}
            public function getHeader($header) { return $header === 'Reply-Text' ? $this->text : ''; }
        };
    }

    public function test_direction_filter_is_acknowledged_before_subscription_on_each_connection(): void
    {
        // Each connection gets its own filters; reusing the PHP service type
        // must not suppress setup on a replacement socket.
        for ($i = 0; $i < 2; $i++) {
            $connection = Mockery::mock();
            $connection->shouldReceive('sendRecv')->once()->ordered()
                ->with('filter variable_direction inbound')->andReturn($this->reply('+OK filter added'));
            $connection->shouldReceive('sendRecv')->once()->ordered()
                ->with('event plain CHANNEL_CREATE')->andReturn($this->reply('+OK event listener enabled plain'));
            $this->assertTrue($this->service($connection)->subscribeToEvents('plain', 'CHANNEL_CREATE', ['variable_direction' => 'inbound']));
        }
    }

    public function test_existing_unfiltered_subscription_keeps_all_requested_event_types(): void
    {
        $events = 'CHANNEL_CREATE CHANNEL_ANSWER CHANNEL_HANGUP_COMPLETE CUSTOM callcenter::info';
        $connection = Mockery::mock();
        $connection->shouldReceive('sendRecv')->once()->with('event plain '.$events)
            ->andReturn($this->reply('+OK event listener enabled plain'));
        $this->assertTrue($this->service($connection)->subscribeToEvents('plain', $events));
    }

    /** @dataProvider failedReplies */
    public function test_failed_or_missing_acknowledgement_disconnects_without_unfiltered_fallback(bool $filterAccepted, ?string $reply): void
    {
        $connection = Mockery::mock();
        $connection->shouldReceive('sendRecv')->once()->ordered()->with('filter variable_direction inbound')
            ->andReturn($this->reply($filterAccepted ? '+OK filter added' : $reply));
        if ($filterAccepted) {
            $connection->shouldReceive('sendRecv')->once()->ordered()->with('event plain CHANNEL_CREATE')
                ->andReturn($this->reply($reply));
        }
        $connection->shouldReceive('disconnect')->once()->ordered();
        $this->assertFalse($this->service($connection)->subscribeToEvents('plain', 'CHANNEL_CREATE', ['variable_direction' => 'inbound']));
    }

    public static function failedReplies(): array
    {
        return [[false, '-ERR invalid syntax'], [false, null], [false, ''],
            [true, '-ERR no keywords supplied'], [true, null], [true, '']];
    }
}
