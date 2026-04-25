<?php

namespace App\Http\Controllers\Api\V1;

use App\Data\Api\V1\CallFlow\CallFlowSimulationData;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Services\CallFlow\CallFlowMermaidRenderer;
use App\Services\CallFlow\CallFlowSimulator;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\Request;

class CallFlowSimulationController extends Controller
{
    public function __construct(
        protected CallFlowSimulator $simulator,
        protected CallFlowMermaidRenderer $mermaid,
    ) {}

    /**
     * Simulate the call flow for an inbound phone number at a point in time.
     *
     * Returns a tree showing every branch the call could take (business
     * hours, IVR digit options, ring-group member progression, extension
     * no-answer → voicemail, etc.). Branches are labelled with their trigger
     * ("in_hours", "press_2", "no_answer") and the branch that is active
     * right now for the supplied timestamp is marked `active: true`.
     *
     * Access rules:
     * - Tenant token: must match the domain_uuid in the URL.
     * - Global token: may access any domain.
     * - Caller must have the `call_flow_simulate` permission.
     *
     * @group Call Flow
     * @authenticated
     *
     * @urlParam domain_uuid string required The domain UUID.
     *
     * @queryParam phone_number string required Inbound DID to simulate. Accepts E.164 (+441225800810) or national digits. Example: +441225800810
     * @queryParam at string ISO 8601 timestamp to evaluate at; defaults to now. Example: 2026-04-23T19:00:00Z
     * @queryParam max_depth int Max tree depth (1–50, default 20).
     * @queryParam format string One of: `json` (default), `mermaid`. Mermaid returns text/plain for diagram rendering.
     */
    public function simulate(Request $request, string $domain_uuid)
    {
        $this->assertUuid($domain_uuid, 'domain_uuid');
        return $this->run($request, $domain_uuid);
    }

    /**
     * Global simulate — any domain, global admin token required.
     *
     * @group Call Flow
     * @authenticated
     *
     * @queryParam domain_uuid string required The domain UUID.
     * @queryParam phone_number string required Inbound DID to simulate.
     * @queryParam at string ISO 8601 timestamp to evaluate at; defaults to now.
     * @queryParam max_depth int Max tree depth (1–50, default 20).
     * @queryParam format string One of: `json` (default), `mermaid`.
     */
    public function globalSimulate(Request $request)
    {
        $domainUuid = trim((string) $request->query('domain_uuid', ''));
        if ($domainUuid === '') {
            throw new ApiException(
                400,
                'invalid_request_error',
                'domain_uuid is required.',
                'parameter_missing',
                'domain_uuid',
            );
        }
        $this->assertUuid($domainUuid, 'domain_uuid');
        return $this->run($request, $domainUuid);
    }

    private function run(Request $request, string $domainUuid)
    {
        $phoneNumber = trim((string) $request->query('phone_number', ''));
        if ($phoneNumber === '') {
            throw new ApiException(
                400,
                'invalid_request_error',
                'phone_number is required.',
                'parameter_missing',
                'phone_number',
            );
        }
        if (strlen($phoneNumber) > 32) {
            throw new ApiException(
                400,
                'invalid_request_error',
                'phone_number is too long.',
                'invalid_request',
                'phone_number',
            );
        }

        $at = $this->parseAt($request);
        $maxDepth = $this->parseMaxDepth($request);

        $format = strtolower((string) $request->query('format', 'json'));
        if (! in_array($format, ['json', 'mermaid'], true)) {
            throw new ApiException(
                400,
                'invalid_request_error',
                'format must be one of json, mermaid.',
                'invalid_request',
                'format',
            );
        }

        $simulation = $this->simulator->simulate($domainUuid, $phoneNumber, $at, $maxDepth);

        if ($format === 'mermaid') {
            return response($this->mermaid->render($simulation), 200)
                ->header('Content-Type', 'text/plain; charset=utf-8');
        }

        return response()->json($simulation->toArray(), 200);
    }

    private function parseAt(Request $request): DateTimeImmutable
    {
        $raw = trim((string) $request->query('at', ''));
        if ($raw === '') {
            return new DateTimeImmutable('now', new DateTimeZone('UTC'));
        }
        try {
            return new DateTimeImmutable($raw);
        } catch (\Exception $e) {
            throw new ApiException(
                400,
                'invalid_request_error',
                'at must be a valid ISO 8601 timestamp.',
                'invalid_request',
                'at',
            );
        }
    }

    private function parseMaxDepth(Request $request): int
    {
        $v = (int) $request->query('max_depth', 20);
        return max(1, min(50, $v));
    }

    private function assertUuid(string $value, string $field): void
    {
        if (! preg_match('/^[0-9a-fA-F-]{36}$/', $value)) {
            throw new ApiException(
                400,
                'invalid_request_error',
                'Invalid ' . $field . '.',
                'invalid_request',
                $field,
            );
        }
    }
}
