<?php

namespace Tests\Unit;

use App\Jobs\SendFaxJob;
use ReflectionMethod;
use Tests\TestCase;

class SendFaxJobTest extends TestCase
{
    public function test_originate_variables_export_fax_webhook_fields_to_the_gateway_leg(): void
    {
        $job = new SendFaxJob('00000000-0000-0000-0000-000000000001');
        $method = new ReflectionMethod($job, 'buildOriginateVariableBlock');

        $block = $method->invoke($job, [
            'fax_uuid=00000000-0000-0000-0000-000000000002',
            "api_hangup_hook='lua lua/fax_hangup.lua'",
            "export_vars='custom_fax_variable,fax_uuid'",
        ]);

        $this->assertStringContainsString(
            "export_vars='custom_fax_variable,fax_uuid,api_hangup_hook,outbound_fax_uuid,outbound_fax_attempt_uuid,domain_uuid,domain_name,call_direction,fax_file,fax_uri,caller_destination,fax_retry_attempts,fax_retry_limit,fax_ident,fax_header'",
            $block
        );
        $this->assertSame(1, substr_count($block, 'export_vars='));
    }
}
