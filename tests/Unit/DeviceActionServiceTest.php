<?php

namespace Tests\Unit;

use App\Jobs\SendEventNotify;
use App\Services\DeviceActionService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DeviceActionServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Log::spy();
        Queue::fake();
    }

    public function test_large_site_is_spread_at_five_actions_per_minute(): void
    {
        $registrations = collect(range(1, 40))
            ->map(fn (int $number) => $this->registration(
                "phone-{$number}",
                '198.51.100.10'
            ));

        $scheduled = app(DeviceActionService::class)
            ->scheduleDeviceActions($registrations, 'reboot');

        $this->assertSame(40, $scheduled);
        $this->assertSame(
            range(0, 468, 12),
            $this->delays()
        );
    }

    public function test_small_site_is_not_queued_behind_large_site(): void
    {
        $registrations = collect(range(1, 40))
            ->map(fn (int $number) => $this->registration(
                "large-{$number}",
                '198.51.100.10'
            ))
            ->concat([
                $this->registration('small-1', '203.0.113.20'),
                $this->registration('small-2', '203.0.113.20'),
            ]);

        app(DeviceActionService::class)
            ->scheduleDeviceActions($registrations, 'reboot');

        $smallSiteDelays = Queue::pushed(SendEventNotify::class)
            ->filter(fn (SendEventNotify $job) => str_contains($job->command, 'small-'))
            ->map(fn (SendEventNotify $job) => (int) ($job->delay ?? 0))
            ->values()
            ->all();

        $this->assertSame([2, 14], $smallSiteDelays);
    }

    public function test_one_thousand_phones_across_sites_finish_dispatching_in_about_twenty_minutes(): void
    {
        $registrations = collect(range(1, 25))
            ->flatMap(function (int $site) {
                return collect(range(1, 40))
                    ->map(fn (int $phone) => $this->registration(
                        "site-{$site}-phone-{$phone}",
                        "198.51.{$site}.10"
                    ));
            });

        $scheduled = app(DeviceActionService::class)
            ->scheduleDeviceActions($registrations, 'reboot');

        $delays = $this->delays();

        $this->assertSame(1000, $scheduled);
        $this->assertCount(1000, $delays);
        $this->assertGreaterThanOrEqual(1198, max($delays));
        $this->assertLessThanOrEqual(1200, max($delays));
    }

    /**
     * @return array<string, string>
     */
    private function registration(string $user, string $wanIp): array
    {
        return [
            'agent' => 'Polycom VVX 450',
            'wan_ip' => $wanIp,
            'sip_profile_name' => 'internal',
            'user' => "{$user}@example.test",
            'call_id' => "call-{$user}",
        ];
    }

    /**
     * @return int[]
     */
    private function delays(): array
    {
        return Queue::pushed(SendEventNotify::class)
            ->map(fn (SendEventNotify $job) => (int) ($job->delay ?? 0))
            ->values()
            ->all();
    }
}
