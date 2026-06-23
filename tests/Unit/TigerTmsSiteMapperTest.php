<?php

namespace Tests\Unit;

use App\Services\TigerTmsSiteMapper;
use Tests\TestCase;

class TigerTmsSiteMapperTest extends TestCase
{
    public function test_inbound_test_site_maps_to_test_domain_uuid(): void
    {
        config([
            'tigertms.test_site_id' => '001',
            'tigertms.test_domain_uuid' => '7d58342b-2b29-4dcf-92d6-e9a9e002a4e5',
        ]);

        $this->assertSame(
            '7d58342b-2b29-4dcf-92d6-e9a9e002a4e5',
            app(TigerTmsSiteMapper::class)->inbound('001')
        );
    }

    public function test_inbound_uuid_site_is_used_unchanged(): void
    {
        $uuid = '11111111-2222-3333-4444-555555555555';

        $this->assertSame($uuid, app(TigerTmsSiteMapper::class)->inbound($uuid));
    }

    public function test_inbound_unknown_site_is_unprocessable(): void
    {
        $this->assertNull(app(TigerTmsSiteMapper::class)->inbound('hotel-alpha'));
    }

    public function test_outbound_test_domain_maps_to_test_site(): void
    {
        config([
            'tigertms.test_site_id' => '001',
            'tigertms.test_domain_uuid' => '7d58342b-2b29-4dcf-92d6-e9a9e002a4e5',
        ]);

        $this->assertSame(
            '001',
            app(TigerTmsSiteMapper::class)->outbound('7d58342b-2b29-4dcf-92d6-e9a9e002a4e5')
        );
    }

    public function test_outbound_production_domain_uuid_is_used_unchanged(): void
    {
        $uuid = '11111111-2222-3333-4444-555555555555';

        $this->assertSame($uuid, app(TigerTmsSiteMapper::class)->outbound($uuid));
    }
}
