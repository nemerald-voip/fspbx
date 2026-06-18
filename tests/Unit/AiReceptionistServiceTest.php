<?php

namespace Tests\Unit;

use App\Models\AiReceptionist;
use App\Models\AiReceptionistRoute;
use App\Models\AiReceptionistSession;
use App\Models\AiReceptionistWarmTransfer;
use App\Models\Domain;
use App\Services\FreeswitchEslService;
use App\Services\AiReceptionistService;
use ReflectionMethod;
use Tests\TestCase;

class AiReceptionistServiceTest extends TestCase
{
    public function test_match_phrases_normalization_handles_empty_and_tag_payloads(): void
    {
        $method = new ReflectionMethod(AiReceptionistService::class, 'normalizeMatchPhrases');
        $method->setAccessible(true);

        $service = app(AiReceptionistService::class);

        $this->assertSame([], $method->invoke($service, null));
        $this->assertSame([], $method->invoke($service, []));
        $this->assertSame(['bill question', 'support'], $method->invoke($service, [
            ['value' => ' bill question '],
            ['label' => 'support'],
            'SUPPORT',
            '',
        ]));
        $this->assertSame(['billing', 'sales'], $method->invoke($service, "billing\nsales, billing"));
    }

    public function test_collected_field_values_normalization_keeps_named_answers(): void
    {
        $method = new ReflectionMethod(AiReceptionistService::class, 'normalizeCollectedFieldValues');
        $method->setAccessible(true);

        $service = app(AiReceptionistService::class);

        $this->assertSame([], $method->invoke($service, null));
        $this->assertSame([
            'Account Number' => '12345',
            'Location' => 'Suite 9',
        ], $method->invoke($service, [
            'Account Number' => ' 12345 ',
            ['name' => 'Location', 'value' => ' Suite 9 '],
            'blank' => '',
        ]));
    }

    public function test_warm_transfer_originate_variables_quote_values_and_skip_blanks(): void
    {
        $method = new ReflectionMethod(AiReceptionistService::class, 'eslOriginateVariables');
        $method->setAccessible(true);

        $variables = $method->invoke(app(AiReceptionistService::class), [
            'origination_context' => 'admin.localhost',
            'effective_caller_id_name' => "Emma's AI Receptionist",
            'empty' => '',
        ]);

        $this->assertSame(
            "{origination_context='admin.localhost',effective_caller_id_name='Emma\\'s AI Receptionist'}",
            $variables
        );
    }

    public function test_warm_transfer_caller_id_uses_receptionist_name_and_original_caller_number(): void
    {
        $service = app(AiReceptionistService::class);
        $nameMethod = new ReflectionMethod(AiReceptionistService::class, 'warmTransferCallerIdName');
        $numberMethod = new ReflectionMethod(AiReceptionistService::class, 'warmTransferCallerIdNumber');
        $nameMethod->setAccessible(true);
        $numberMethod->setAccessible(true);

        $receptionist = new AiReceptionist([
            'name' => 'Emma Receptionist',
            'extension' => '9451',
        ]);
        $session = new AiReceptionistSession([
            'caller_id_number' => '100',
            'destination_number' => '9451',
        ]);
        $session->setRelation('receptionist', $receptionist);

        $this->assertSame('Emma Receptionist', $nameMethod->invoke($service, $session));
        $this->assertSame('100', $numberMethod->invoke($service, $session));
    }

    public function test_warm_transfer_extension_dial_string_uses_direct_user_leg(): void
    {
        $domain = new Domain([
            'domain_uuid' => '33333333-3333-4333-8333-333333333333',
            'domain_name' => 'admin.localhost',
            'domain_enabled' => true,
        ]);

        $method = new ReflectionMethod(AiReceptionistService::class, 'warmTransferDialString');
        $method->setAccessible(true);

        $route = new AiReceptionistRoute([
            'destination_type' => 'extensions',
            'destination_target' => '101',
        ]);
        $session = new AiReceptionistSession([
            'domain_uuid' => '33333333-3333-4333-8333-333333333333',
        ]);
        $session->setRelation('domain', $domain);

        $this->assertSame(
            'user/101@admin.localhost',
            $method->invoke(app(AiReceptionistService::class), $route, $session)
        );
    }

    public function test_warm_transfer_resolves_live_consult_peer_when_stored_loopback_leg_is_gone(): void
    {
        $this->app->instance(FreeswitchEslService::class, new class {
            public function executeCommand($cmd, $disconnect = true)
            {
                return match ($cmd) {
                    'uuid_getvar consult-uuid bridge_uuid' => 'external-uuid',
                    'uuid_exists external-uuid' => 'true',
                    'uuid_getvar external-uuid channel_name' => 'sofia/external/16467052267',
                    'uuid_getvar external-uuid bridge_uuid' => 'consult-uuid',
                    default => '_undef_',
                };
            }
        });

        $method = new ReflectionMethod(AiReceptionistService::class, 'activeWarmTransferRecipientUuid');
        $method->setAccessible(true);

        $warmTransfer = new AiReceptionistWarmTransfer([
            'caller_uuid' => 'caller-uuid',
            'openai_uuid' => 'caller-openai-uuid',
            'consult_freeswitch_uuid' => 'consult-uuid',
            'recipient_uuid' => 'dead-loopback-uuid',
        ]);

        $this->assertSame(
            'external-uuid',
            $method->invoke(app(AiReceptionistService::class), $warmTransfer)
        );
    }
}
