<?php

namespace Tests\Unit;

use App\Services\FreeswitchEslService;
use PHPUnit\Framework\TestCase;

class FreeswitchEslConnectionTest extends TestCase
{
    public function test_raw_connection_is_reused_and_reconnected_only_when_disconnected(): void
    {
        $connection = new class {
            public bool $online = true;
            public function connected() { return $this->online; }
            public function disconnect() { $this->online = false; }
        };
        $service = new class($connection) extends FreeswitchEslService {
            public int $reconnects = 0;
            public function __construct($connection) { $this->conn = $connection; }
            public function reconnect(): void { $this->reconnects++; $this->conn = clone $this->conn; $this->conn->online = true; }
        };
        $this->assertSame($connection, $service->connection());
        $this->assertSame($connection, $service->connection());
        $this->assertSame(0, $service->reconnects);
        $service->disconnect();
        $new = $service->connection();
        $this->assertNotSame($connection, $new);
        $this->assertSame(1, $service->reconnects);
        $this->assertSame($new, $service->connection());
        $this->assertSame(1, $service->reconnects);
    }

    public function test_raw_connection_propagates_reconnection_failure(): void
    {
        $service = new class extends FreeswitchEslService {
            public function __construct() {}
            public function reconnect(): void { throw new \RuntimeException('ESL unavailable'); }
        };
        $this->expectExceptionMessage('ESL unavailable');
        $service->connection();
    }
}
