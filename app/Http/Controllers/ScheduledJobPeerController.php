<?php

namespace App\Http\Controllers;

use App\Models\ScheduledJobHandoff;
use App\Models\ScheduledJobNode;
use App\Services\Ha\ActiveNodeResolver;
use App\Services\Ha\ScheduledJobPeerAuthenticator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ScheduledJobPeerController extends Controller
{
    public function __construct(
        private readonly ActiveNodeResolver $resolver,
        private readonly ScheduledJobPeerAuthenticator $authenticator
    ) {}

    public function identify(Request $request): JsonResponse
    {
        if (! $request->isSecure()) {
            return response()->json(['message' => 'HTTPS is required for scheduled-job coordination.'], 426);
        }
        if (! $this->authenticator->verifyRequest($request)) {
            return response()->json(['message' => 'Invalid scheduled-job peer signature.'], 403);
        }
        $values = $request->validate([
            'challenge' => ['required', 'string', 'size:32'],
            'endpoint_identity' => ['required', 'url', 'max:2048'],
        ]);
        $payload = [
            'system_identifier' => $this->resolver->localNodeId(),
            'host_fingerprint' => $this->resolver->hostFingerprint(),
            'coordination' => $this->resolver->coordinationSnapshot(),
            'hostname' => $this->resolver->hostname(),
            'capabilities' => ['node_approval', 'node_removal'],
            'version' => (string) config('app.version', ''),
            'challenge' => $values['challenge'],
            'request_nonce' => (string) $request->header(ScheduledJobPeerAuthenticator::NONCE_HEADER),
            'endpoint_identity' => rtrim($values['endpoint_identity'], '/'),
        ];

        return $this->signedResponse($request, $payload);
    }

    public function approveNode(Request $request): JsonResponse
    {
        if (! $request->isSecure()) {
            return response()->json(['message' => 'HTTPS is required for scheduled-job coordination.'], 426);
        }
        if (! $this->authenticator->verifyRequest($request)) {
            return response()->json(['message' => 'Invalid scheduled-job peer signature.'], 403);
        }

        try {
            $values = $request->validate([
                'writer_node_id' => ['required', 'string', 'regex:/^\d{1,32}$/'],
                'target_node_id' => ['required', 'string', 'regex:/^\d{1,32}$/'],
                'target_endpoint' => ['required', 'string', 'max:2048'],
                'target_host_fingerprint' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/'],
                'requested_by' => ['nullable', 'uuid'],
            ]);
            if ($values['writer_node_id'] !== $this->resolver->localNodeId()) {
                throw new RuntimeException(__('The approval request reached a different server. Refresh status and try again.'), 409);
            }
            // Never forward a forwarded request. The resolver rechecks authority
            // under the ownership lock and freshly verifies the target identity.
            $node = $this->resolver->approveNode(
                $values['target_endpoint'], $values['target_node_id'],
                $values['requested_by'] ?? null, $values['target_host_fingerprint']
            );

            return $this->signedResponse($request, ['node' => $node->toArray()]);
        } catch (ValidationException $exception) {
            return $this->signedResponse($request, ['message' => $exception->getMessage(), 'errors' => $exception->errors()], 422);
        } catch (RuntimeException $exception) {
            return $this->signedResponse($request, ['message' => $exception->getMessage()], $exception->getCode() === 409 ? 409 : 422);
        }
    }

    public function removeNode(Request $request): JsonResponse
    {
        if (! $request->isSecure()) {
            return response()->json(['message' => 'HTTPS is required for scheduled-job coordination.'], 426);
        }
        if (! $this->authenticator->verifyRequest($request)) {
            return response()->json(['message' => 'Invalid scheduled-job peer signature.'], 403);
        }

        try {
            $values = $request->validate([
                'writer_node_id' => ['required', 'string', 'regex:/^\d{1,32}$/'],
                'target_node_id' => ['required', 'string', 'regex:/^\d{1,32}$/'],
                'node_uuid' => ['required', 'uuid'],
            ]);
            if ($values['writer_node_id'] !== $this->resolver->localNodeId()) {
                throw new RuntimeException(__('The removal request reached a different server. Refresh status and try again.'), 409);
            }
            $node = ScheduledJobNode::query()->whereKey($values['node_uuid'])->first();
            if ($node && $node->system_identifier !== $values['target_node_id']) {
                throw new RuntimeException(__('The server registration was not found. Refresh status and try again.'), 409);
            }
            // Check authority even when retrying a removal that already completed.
            $target = $node ?? (new ScheduledJobNode())->forceFill(['scheduled_job_node_uuid' => $values['node_uuid']]);
            $this->resolver->removeNode($target);

            return $this->signedResponse($request, ['node_uuid' => $values['node_uuid'], 'target_node_id' => $values['target_node_id']]);
        } catch (ValidationException $exception) {
            return $this->signedResponse($request, ['message' => $exception->getMessage(), 'errors' => $exception->errors()], 422);
        } catch (RuntimeException $exception) {
            return $this->signedResponse($request, ['message' => $exception->getMessage()], $exception->getCode() === 409 ? 409 : 422);
        }
    }

    public function prepareHandoff(Request $request): JsonResponse
    {
        if (! $request->isSecure()) {
            return response()->json(['message' => 'HTTPS is required for scheduled-job coordination.'], 426);
        }
        if (! $this->authenticator->verifyRequest($request)) {
            return response()->json(['message' => 'Invalid scheduled-job peer signature.'], 403);
        }

        try {
            $values = $request->validate([
                'target_node_id' => ['required', 'string', 'max:32'],
                'target_endpoint' => ['required', 'string', 'max:2048'],
                'expected_generation' => ['required', 'integer', 'min:0'],
                'requested_by' => ['nullable', 'uuid'],
                'idempotency_key' => ['required', 'uuid'],
            ]);
            if ($values['idempotency_key'] !== $request->header(ScheduledJobPeerAuthenticator::IDEMPOTENCY_HEADER)) {
                throw new RuntimeException('The signed idempotency header does not match the request.');
            }
            $result = $this->resolver->prepareHandoff($values);

            return $this->signedResponse($request, $result, ($result['status'] ?? null) === 'draining' ? 202 : 200);
        } catch (RuntimeException $exception) {
            return $this->signedResponse($request, [
                'message' => $exception->getMessage(),
                'generation' => $this->resolver->generation(),
                'active_node' => $this->resolver->configuredNode(),
            ], $exception->getCode() === 409 ? 409 : 422);
        }
    }

    public function handoffStatus(Request $request, string $handoff): JsonResponse
    {
        if (! $request->isSecure()) {
            return response()->json(['message' => 'HTTPS is required for scheduled-job coordination.'], 426);
        }
        if (! $this->authenticator->verifyRequest($request)) {
            return response()->json(['message' => 'Invalid scheduled-job peer signature.'], 403);
        }
        $handoff = ScheduledJobHandoff::query()->whereKey($handoff)->orWhere('idempotency_key', $handoff)->first();
        if (! $handoff) {
            return $this->signedResponse($request, ['message' => 'Ownership request not found.'], 404);
        }

        return $this->signedResponse($request, [
            'status' => $handoff->status,
            'handoff' => [
                'id' => $handoff->scheduled_job_handoff_uuid,
                'from_node_id' => $handoff->from_node_id,
                'to_node_id' => $handoff->to_node_id,
                'expected_generation' => $handoff->expected_generation,
                'status' => $handoff->status,
                'forced' => $handoff->forced,
                'message' => $handoff->message,
            ],
        ]);
    }

    private function signedResponse(Request $request, array $payload, int $status = 200): JsonResponse
    {
        $headers = $this->authenticator->responseHeaders(
            $request->path(),
            $payload,
            (string) $request->header(ScheduledJobPeerAuthenticator::NONCE_HEADER),
            (string) $request->header(ScheduledJobPeerAuthenticator::IDEMPOTENCY_HEADER),
            $status
        );

        return response()->json($payload, $status, $headers);
    }
}
