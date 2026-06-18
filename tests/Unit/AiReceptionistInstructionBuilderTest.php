<?php

namespace Tests\Unit;

use App\Models\AiReceptionist;
use App\Models\AiReceptionistRoute;
use App\Services\AiReceptionistInstructionBuilder;
use Illuminate\Support\Collection;
use Tests\TestCase;

class AiReceptionistInstructionBuilderTest extends TestCase
{
    public function test_caller_instructions_expose_only_three_caller_tools(): void
    {
        $receptionist = new AiReceptionist([
            'system_prompt' => 'Use a calm tone.',
        ]);

        $routes = new Collection([
            new AiReceptionistRoute([
                'route_uuid' => 'cold-route',
                'name' => 'Sales',
                'match_phrases' => ['sales', 'pricing'],
                'action_type' => 'transfer',
                'transfer_type' => 'cold',
                'destination_label' => 'Sales Queue',
            ]),
            new AiReceptionistRoute([
                'route_uuid' => 'warm-route',
                'name' => 'Support',
                'match_phrases' => ['support', 'technical support'],
                'collected_fields' => ['account number', 'issue summary'],
                'action_type' => 'transfer',
                'transfer_type' => 'warm',
                'destination_label' => 'Support Desk',
            ]),
            new AiReceptionistRoute([
                'route_uuid' => 'email-route',
                'name' => 'Billing Message',
                'match_phrases' => ['billing'],
                'action_type' => 'email',
            ]),
        ]);

        $instructions = app(AiReceptionistInstructionBuilder::class)
            ->callerInstructions($receptionist, $routes);

        $this->assertStringContainsString('cold_transfer', $instructions);
        $this->assertStringContainsString('warm_transfer', $instructions);
        $this->assertStringContainsString('send_email', $instructions);
        $this->assertStringContainsString('Route names are primary triggers.', $instructions);
        $this->assertStringContainsString('If a route name conflicts with an additional match phrase, prefer the route name the caller actually said.', $instructions);
        $this->assertStringContainsString('route_name_trigger=Support; additional_match_phrases=technical support; collected_fields=account number, issue summary', $instructions);
        $this->assertStringContainsString('warm_transfer(route_uuid, handoff_summary, collected_fields)', $instructions);
        $this->assertStringContainsString('Do not ask about unrelated routes.', $instructions);
        $this->assertStringContainsString('Do not ask again for a detail the caller already gave.', $instructions);
        $this->assertStringContainsString('Treat phrases like "This is [caller name] with [company name]" as both caller name and company', $instructions);
        $this->assertStringContainsString('After send_email succeeds, say one final confirmation and goodbye.', $instructions);
        $this->assertStringNotContainsString('transfer_call', $instructions);
        $this->assertStringNotContainsString('warm_transfer_call', $instructions);
        $this->assertStringNotContainsString('send_route_email', $instructions);
        $this->assertStringNotContainsString('run_http_tool', $instructions);
    }

    public function test_consult_instructions_are_private_and_use_internal_decision_tools(): void
    {
        $route = new AiReceptionistRoute([
            'name' => 'Support',
            'destination_label' => 'Support Desk',
        ]);

        $instructions = app(AiReceptionistInstructionBuilder::class)
            ->consultInstructions($route, 'Caller needs help with a login issue.', [
                'Account Number' => '12345',
                'Issue Summary' => 'Portal login error',
            ]);

        $this->assertStringContainsString('accept_transfer', $instructions);
        $this->assertStringContainsString('decline_transfer', $instructions);
        $this->assertStringContainsString('The original caller cannot hear you.', $instructions);
        $this->assertStringContainsString('You already have the caller summary in this prompt.', $instructions);
        $this->assertStringContainsString('Collected route details: Account Number: 12345; Issue Summary: Portal login error', $instructions);
        $this->assertStringContainsString('Never say "I am ready"', $instructions);
    }

    public function test_consult_initial_message_is_clean_and_does_not_repeat_transfer_boilerplate(): void
    {
        $route = new AiReceptionistRoute([
            'name' => 'Support',
            'destination_label' => '101 - Elena Dawson',
        ]);

        $message = app(AiReceptionistInstructionBuilder::class)->consultInitialMessage(
            $route,
            'Caller is requesting Support for assistance; transferring to Elena Dawson for support help.',
            'Emma'
        );

        $this->assertStringContainsString('Hi, this is Emma, the AI receptionist.', $message);
        $this->assertStringContainsString('I have a caller asking for Support.', $message);
        $this->assertStringContainsString('Can you take the call now?', $message);
        $this->assertStringNotContainsString('101 - Elena Dawson', $message);
        $this->assertStringNotContainsString('transferring to Elena Dawson', $message);
    }

    public function test_consult_initial_message_keeps_meaningful_issue_details(): void
    {
        $route = new AiReceptionistRoute([
            'name' => 'Support',
            'destination_label' => '101 - Elena Dawson',
        ]);

        $message = app(AiReceptionistInstructionBuilder::class)->consultInitialMessage(
            $route,
            'Caller needs help with a login issue.',
            'Emma',
            [
                'Account Number' => '12345',
                'Issue Summary' => 'Portal login error',
            ]
        );

        $this->assertStringContainsString('I have a caller asking for Support. Caller needs help with a login issue.', $message);
        $this->assertStringContainsString('Details collected: Account Number: 12345; Issue Summary: Portal login error.', $message);
    }
}
