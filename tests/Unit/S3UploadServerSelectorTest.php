<?php

namespace Tests\Unit;

use App\Services\Ha\ActiveNodeResolver;
use App\Services\S3UploadServerSelector;
use Mockery;
use Tests\TestCase;

class S3UploadServerSelectorTest extends TestCase
{
    /** @dataProvider selections */
    public function test_selection_preserves_legacy_enablement_and_prefers_definite_ownership(
        array $settings, string $status, bool $available, bool $allowed, bool $coordinated
    ): void {
        $resolver = Mockery::mock(ActiveNodeResolver::class);
        $resolver->shouldReceive('resolve')->andReturn([
            'active' => $status === 'active', 'status' => $status, 'reason' => $status,
        ]);
        $selector = new class($resolver, $available) extends S3UploadServerSelector {
            public function __construct(ActiveNodeResolver $resolver, private bool $available)
            {
                parent::__construct($resolver);
            }
            protected function coordinationAvailable(): bool { return $this->available; }
            public function macAddress(): ?string { return 'aa:bb:cc:dd:ee:ff'; }
        };

        $result = $selector->resolve($settings);
        $this->assertSame($allowed, $result['allowed']);
        $this->assertSame($coordinated, $result['coordinated']);
    }

    public static function selections(): array
    {
        $local = ['s3_upload_calls_aa:bb:cc:dd:ee:ff' => 'true'];
        $remote = ['s3_upload_calls_11:22:33:44:55:66' => 'true'];
        $shared = ['s3_upload_calls' => 'true'];
        return [
            'disabled standalone' => [[], 'active', true, false, false],
            'standalone enabled globally' => [$shared, 'active', true, true, true],
            'standalone legacy enabled' => [$local, 'active', true, true, true],
            'owner overrides remote MAC' => [$remote, 'active', true, true, true],
            'standby ignores local MAC' => [$local, 'standby', true, false, true],
            'draining ignores local MAC' => [$local, 'draining', true, false, true],
            'unconfigured uses local MAC' => [$local, 'active_unknown', true, true, false],
            'unconfigured rejects remote MAC' => [$remote, 'active_unknown', true, false, false],
            'uncertain shared switch cannot select server' => [$shared, 'active_unknown', true, false, false],
            'missing migrations uses MAC' => [$local, 'active', false, true, false],
            'missing migrations shared only' => [$shared, 'active', false, false, false],
            'global off overrides legacy' => [$local + ['s3_upload_calls' => 'false'], 'active', true, false, false],
            'global on preserves MAC fallback' => [$shared + $local, 'active_unknown', true, true, false],
            'limit is not enablement' => [['s3_upload_calls_time' => 'true'], 'active', true, false, false],
            'false MAC stays disabled' => [['s3_upload_calls_aa:bb:cc:dd:ee:ff' => 'false'], 'active', true, false, false],
        ];
    }
}
