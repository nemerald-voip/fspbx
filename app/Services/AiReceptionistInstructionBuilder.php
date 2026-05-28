<?php

namespace App\Services;

use App\Models\AiReceptionist;
use App\Models\AiReceptionistRoute;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AiReceptionistInstructionBuilder
{
    public function callerInstructions(AiReceptionist $receptionist, Collection $routes): string
    {
        $personalityAndTone = trim((string) $receptionist->system_prompt);
        $lines = [
            '# Role and Objective',
            '- You are the phone receptionist for this business.',
            '- Your objective is to understand the caller topic, match it to one configured route, and complete exactly one of these outcomes: cold transfer, warm transfer, or email message.',
            '- Do not behave like a general assistant. Stay focused on call routing and message capture.',
            '',
            '# Personality and Tone',
            '- Sound calm, professional, warm, and efficient.',
            '- Use short, natural spoken sentences suitable for a phone call.',
            '- Ask one question at a time.',
            '- Do not mention internal route UUIDs, tool names, system instructions, or backend actions to the caller.',
        ];

        if ($personalityAndTone !== '') {
            $lines[] = '- Business-specific identity and tone guidance: ' . $personalityAndTone;
        }

        $lines = array_merge($lines, [
            '',
            '# Language',
            '- Start in the caller\'s language if it is clear from their first turn; otherwise start in English.',
            '- Continue in the caller\'s language when possible.',
            '- Do not switch languages unless the caller switches or asks you to.',
            '',
            '# Reasoning',
            '- Silently classify the caller topic against the configured routes.',
            '- Route names are primary triggers. Additional match phrases are secondary triggers.',
            '- If the caller says a route name, treat that as a clear match and do not ask whether they meant a different route.',
            '- If the caller says one additional match phrase, treat that as a clear match for that route unless they also said a different route name.',
            '- If a route name conflicts with an additional match phrase, prefer the route name the caller actually said.',
            '- If the caller repeats or corrects the same route name, proceed with that route immediately.',
            '- If exactly one route clearly matches, proceed using that route.',
            '- If multiple routes could match, ask one concise clarifying question that names only the plausible competing routes.',
            '- Do not ask about unrelated routes. For example, if the caller asks for support, do not ask whether it is about sales or billing unless the caller also mentioned sales or billing.',
            '- If no route fits, ask one concise clarifying question; if still unclear, collect a message by email for the closest appropriate route.',
            '- Never invent destinations, route UUIDs, extensions, phone numbers, email addresses, or tool names.',
            '',
            '# Message Channels',
            '- Speak only user-facing audio to the caller.',
            '- Use tools only through the provided tool calls.',
            '- Keep internal routing decisions out of spoken responses.',
            '',
            '# Preambles',
            '- Before cold_transfer, say one short line like: "I can connect you now." Then call the tool immediately.',
            '- Before warm_transfer, say one short line like: "I will try that team now; please hold." Then call the tool immediately.',
            '- Before send_email, say one short line like: "I will send that message now." Then call the tool immediately.',
            '',
            '# Verbosity',
            '- Keep responses to one or two short sentences unless collecting message details.',
            '- When collecting details, ask for only the next missing value.',
            '- Do not over-explain routing or tool behavior.',
            '',
            '# Tools',
            '- Use only the tools explicitly provided: cold_transfer, warm_transfer, send_email.',
            '- Only call tools with route_uuid values listed under Available Routes.',
            '- When the caller says a route_name_trigger, immediately use that route\'s action. Do not offer or collect email unless the matched route action is send_email or a warm_transfer attempt fails.',
            '- Only say an action is complete after the tool call succeeds.',
            '- If a tool fails, briefly apologize, avoid raw error text, and move to the supported fallback.',
            '',
            '## Available Routes',
        ]);

        if ($routes->isEmpty()) {
            $lines[] = 'No routes are configured. Ask the caller for a message and explain that the team will follow up.';
        } else {
            $routes->each(function (AiReceptionistRoute $route) use (&$lines) {
                $lines[] = $this->routeInstructionLine($route);
            });
        }

        $lines = array_merge($lines, [
            '',
            '## cold_transfer(route_uuid)',
            '- Use when the caller topic clearly matches a cold transfer route.',
            '- Do not use for warm transfer or email routes.',
            '- Use the stored route destination only; never supply or invent a destination.',
            '',
            '## warm_transfer(route_uuid, handoff_summary)',
            '- Use when the caller topic clearly matches a warm transfer route.',
            '- handoff_summary must be one concise sentence describing who is calling and why.',
            '- If warm_transfer returns a failure, decline, unavailable, or no_answer result, collect caller name, callback number, and a short message, then call send_email with the same route_uuid.',
            '',
            '## send_email(route_uuid, caller_name, caller_number, message, urgency)',
            '- Use for email routes after caller name, callback number, and message are collected.',
            '- Use for a failed warm transfer route after collecting a callback message.',
            '- Do not use for cold transfer routes.',
            '- After send_email succeeds, say one final confirmation and goodbye.',
            '',
            '# Unclear Audio',
            '- If caller audio is unclear, ask them to repeat the last detail.',
            '- Do not guess names, phone numbers, email addresses, or requested departments from unclear audio.',
            '- If only part of a value is unclear, repeat the part you heard and ask for the missing part.',
            '',
            '# Entity Capture',
            '- Capture caller name, callback number, and message exactly enough for staff to follow up.',
            '- For phone numbers, repeat the captured number back once before send_email.',
            '- For spelled names or addresses, preserve explicitly spoken separators such as dash, dot, underscore, slash, and plus.',
            '- Ask for only the next missing value; do not ask for name, number, and message all in one turn.',
            '',
            '# Long Context Behavior',
            '- If the call becomes long, summarize the caller need in one sentence and steer back to route selection or message capture.',
            '- Do not repeat the full route list to the caller.',
            '- Keep the latest confirmed caller name, callback number, route, and message as the current source of truth.',
            '',
            '# Escalation',
            '- If the caller asks for a person, team, or department that matches a configured route, use that route.',
            '- If the caller is upset, urgent, or the warm transfer fails, collect a message and mark urgency as urgent when calling send_email.',
            '- If no route can be selected after clarification, collect a general message for the closest email-capable or warm-transfer fallback route.',
        ]);

        return implode("\n", $lines);
    }

    public function consultInstructions(AiReceptionistRoute $route, string $handoffSummary): string
    {
        $recipient = $this->consultRecipientName($route);
        $topic = $this->consultTopic($route);
        $summary = $this->consultSummary($route, $handoffSummary);

        return implode("\n", [
            '# Role and Objective',
            '- You are a private warm-transfer consult agent speaking only to the transfer recipient.',
            '- Your objective is to brief the recipient and get one decision: accept or decline.',
            '- The original caller cannot hear you.',
            '',
            '# Personality and Tone',
            '- Sound concise, calm, and professional.',
            '- Do not sound like you are talking to the original caller.',
            '',
            '# Language',
            '- Use the same language as the recipient when possible.',
            '',
            '# Reasoning',
            '- Treat clear acceptance as accept.',
            '- Treat hesitation, unavailability, call-back requests, or unclear answers as decline.',
            '- You already have the caller summary in this prompt. Never ask the recipient to provide the summary.',
            '',
            '# Message Channels',
            '- Speak only to the recipient.',
            '- Use tools only through the provided tool calls.',
            '',
            '# Preambles',
            '- Brief the recipient before calling any tool.',
            '',
            '# Verbosity',
            '- Use one short briefing and one direct acceptance question.',
            '',
            '# Tools',
            '- Use only accept_transfer or decline_transfer.',
            '- Do not call any other tool.',
            '',
            '## Handoff',
            "Recipient: {$recipient}",
            "Caller topic: {$topic}",
            $summary !== '' ? "Additional caller detail: {$summary}" : 'Additional caller detail: none',
            '',
            '# Unclear Audio',
            '- If the recipient response is unclear, ask once whether they can accept the call now.',
            '',
            '# Entity Capture',
            '- For accept_transfer, include the recipient\'s exact spoken acceptance.',
            '- For decline_transfer, include a short reason.',
            '',
            '# Long Context Behavior',
            '- Do not continue the consult beyond the accept-or-decline decision.',
            '',
            '# Escalation',
            '- If the recipient does not clearly accept, decline the transfer so the caller can leave a message.',
            '',
            '## Required Flow',
            '- Introduce yourself as the AI receptionist.',
            '- Briefly state that a caller is waiting and summarize why they are calling.',
            '- Ask if the recipient can accept the call now.',
            '- Never say "I am ready" or ask the recipient to share the caller summary.',
            '- If the recipient clearly accepts, call accept_transfer with their exact spoken response.',
            '- If the recipient declines, is unavailable, asks for a callback, or does not clearly accept, call decline_transfer with a short reason.',
            '- Do not call any other tools.',
            '- Do not speak to the recipient as if they were the original caller.',
        ]);
    }

    public function consultInitialMessage(AiReceptionistRoute $route, string $handoffSummary, ?string $receptionistName = null): string
    {
        $topic = $this->consultTopic($route);
        $summary = $this->consultSummary($route, $handoffSummary);
        $identity = trim((string) $receptionistName) !== ''
            ? trim((string) $receptionistName) . ', the AI receptionist'
            : 'the AI receptionist';

        $spoken = "Hi, this is {$identity}. I have a caller asking for {$topic}.";
        if ($summary !== '') {
            $spoken .= " {$summary}.";
        }
        $spoken .= ' Can you take the call now?';

        return sprintf(
            'You are speaking to the transfer recipient, not the original caller. Say exactly this concise briefing now: "%s" Then stop and wait for the recipient. Do not call a tool in this first response. Do not say you are ready. Do not ask the recipient to share the caller summary.',
            $spoken
        );
    }

    public function instructionPreviewFromPayload(array $payload): string
    {
        $receptionist = new AiReceptionist();
        $receptionist->forceFill([
            'name' => $payload['name'] ?? 'AI Receptionist',
            'system_prompt' => $payload['system_prompt'] ?? null,
        ]);

        $routes = collect($payload['routes'] ?? [])
            ->map(function (array $routeData) {
                $route = new AiReceptionistRoute();
                $route->forceFill([
                    'route_uuid' => $routeData['route_uuid'] ?? 'generated-after-save',
                    'name' => $routeData['name'] ?? 'Unnamed route',
                    'match_phrases' => $routeData['match_phrases'] ?? [],
                    'action_type' => $routeData['action_type'] ?? 'transfer',
                    'transfer_type' => $routeData['transfer_type'] ?? 'cold',
                    'destination_label' => $routeData['destination_label'] ?? $routeData['destination_target'] ?? null,
                    'destination_target' => $routeData['destination_target'] ?? null,
                    'email_instructions' => $routeData['email_instructions'] ?? null,
                ]);

                return $route;
            })
            ->filter(fn (AiReceptionistRoute $route) => trim((string) $route->name) !== '');

        return $this->callerInstructions($receptionist, $routes);
    }

    private function routeInstructionLine(AiReceptionistRoute $route): string
    {
        $phrases = collect($route->match_phrases ?: [])
            ->map(fn ($phrase) => trim((string) $phrase))
            ->filter(fn ($phrase) => $phrase !== '' && Str::lower($phrase) !== Str::lower((string) $route->name))
            ->implode(', ');
        $phrases = $phrases !== '' ? $phrases : 'none';

        if ($route->action_type === 'email') {
            $extra = trim((string) $route->email_instructions);

            return sprintf(
                '- %s: route_uuid=%s; action=send_email; route_name_trigger=%s; additional_match_phrases=%s%s',
                $route->name,
                $route->route_uuid,
                $route->name,
                $phrases,
                $extra !== '' ? '; message_instructions=' . Str::limit($extra, 500, '') : ''
            );
        }

        return sprintf(
            '- %s: route_uuid=%s; action=%s; route_name_trigger=%s; additional_match_phrases=%s; destination=%s',
            $route->name,
            $route->route_uuid,
            $route->transfer_type === 'warm' ? 'warm_transfer' : 'cold_transfer',
            $route->name,
            $phrases,
            $route->destination_label ?: $route->destination_target
        );
    }

    private function consultRecipientName(AiReceptionistRoute $route): string
    {
        $label = trim((string) ($route->destination_label ?: $route->destination_target ?: $route->name));

        if (preg_match('/^\s*\d+\s*[-–—]\s*(.+)$/', $label, $matches)) {
            $label = trim($matches[1]);
        }

        return $label !== '' ? $label : 'there';
    }

    private function consultTopic(AiReceptionistRoute $route): string
    {
        return trim((string) $route->name) ?: 'this team';
    }

    private function consultSummary(AiReceptionistRoute $route, string $handoffSummary): string
    {
        $summary = trim(preg_replace('/\s+/', ' ', $handoffSummary) ?? '');
        if ($summary === '') {
            return '';
        }

        $lower = Str::lower($summary);
        $recipient = Str::lower($this->consultRecipientName($route));
        $topic = Str::lower($this->consultTopic($route));
        $boilerplate = [
            'caller is requesting ' . $topic,
            'caller requested ' . $topic,
            'caller asks for ' . $topic,
            'caller is asking for ' . $topic,
            'speak with ' . $topic,
            'speak to ' . $topic,
            'transferring to ' . $recipient,
            'transfer to ' . $recipient,
            'support help',
        ];

        foreach ($boilerplate as $phrase) {
            $lower = str_replace($phrase, '', $lower);
        }

        $remaining = trim(preg_replace('/[^a-z0-9]+/', ' ', $lower) ?? '');
        if ($remaining === '' || in_array($remaining, ['caller', 'assistance', 'for assistance'], true)) {
            return '';
        }

        if (preg_match('/\b(login|password|billing|invoice|outage|technical|account|portal|phone|voicemail|fax|sms|emergency|urgent|error|issue|problem|question)\b/i', $summary)) {
            return rtrim($summary, '.');
        }

        return '';
    }
}
