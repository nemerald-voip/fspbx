<?php

namespace App\Http\Controllers;

use App\Models\ScheduledJobHandoff;
use App\Models\ScheduledJobNode;
use App\Services\Ha\ActiveNodeResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

class ScheduledJobCoordinationController extends Controller
{
    public function __construct(private readonly ActiveNodeResolver $resolver) {}

    public function show(): JsonResponse
    {
        abort_unless(userCheckPermission('ldap_directory_view'), 403);

        return response()->json(['active_node' => $this->resolver->statusContext()]);
    }

    public function discover(Request $request): JsonResponse
    {
        $this->authorizeManagement();
        $values = $request->validate(['endpoint' => ['nullable', 'string', 'max:2048']]);

        return response()->json(['candidates' => $this->resolver->discover($values['endpoint'] ?? null)]);
    }

    public function approve(Request $request, string $node): JsonResponse
    {
        $this->authorizeManagement();
        $values = $request->validate([
            'endpoint' => ['required', 'string', 'max:2048'],
        ]);
        try {
            $approvedNode = $this->resolver->approveNode($values['endpoint'], $node, session('user_uuid'));
        } catch (RuntimeException $exception) {
            return $this->managementError($exception);
        }

        return response()->json([
            'message' => __('Scheduled-job node approved.'),
            'node' => $approvedNode,
            'active_node' => $this->resolver->statusContext(),
        ]);
    }

    public function retire(ScheduledJobNode $node): JsonResponse
    {
        $this->authorizeManagement();
        try {
            $this->resolver->retireNode($node, session('user_uuid'));
        } catch (RuntimeException $exception) {
            return $this->managementError($exception);
        }

        return response()->json([
            'message' => __('Scheduled-job node retired.'),
            'active_node' => $this->resolver->statusContext(),
        ]);
    }

    public function updateOwner(Request $request): JsonResponse
    {
        $this->authorizeManagement();
        $values = $request->validate([
            'target_node' => ['required', 'string', 'max:32'],
            'expected_generation' => ['required', 'integer', 'min:0'],
            'idempotency_key' => ['required', 'uuid'],
        ]);

        try {
            $result = $this->resolver->requestHandoff(
                $values['target_node'],
                (int) $values['expected_generation'],
                session('user_uuid'),
                $values['idempotency_key']
            );
        } catch (RuntimeException $exception) {
            $status = in_array($exception->getCode(), [409, 422], true) ? $exception->getCode() : 422;

            return response()->json([
                'message' => $exception->getMessage(),
                'active_node' => $this->resolver->statusContext(),
            ], $status);
        }

        return response()->json([
            'message' => ($result['status'] ?? null) === 'draining'
                ? __('Ownership transfer started. The current server is draining running jobs.')
                : __('Ownership request acknowledged. The new owner starts after replication delivers the update.'),
            'handoff' => $result['handoff'] ?? null,
            'active_node' => $this->resolver->statusContext(),
        ], ($result['status'] ?? null) === 'draining' ? 202 : 200);
    }

    public function force(Request $request): JsonResponse
    {
        $this->authorizeManagement();
        $values = $request->validate([
            'target_node' => ['required', 'string', 'max:32'],
            'expected_generation' => ['required', 'integer', 'min:0'],
            'fenced_endpoint' => ['required', 'string', 'max:2048'],
            'confirmed' => ['required', Rule::in([true, 1, '1'])],
        ]);

        try {
            $handoff = $this->resolver->forceTakeover(
                $values['target_node'],
                (int) $values['expected_generation'],
                $values['fenced_endpoint'],
                session('user_uuid')
            );
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], $exception->getCode() === 409 ? 409 : 422);
        }

        return response()->json([
            'message' => __('Forced takeover recorded. Keep the previous server fenced until replication is repaired.'),
            'handoff' => $handoff,
            'active_node' => $this->resolver->statusContext(),
        ]);
    }

    public function forceHandoff(Request $request, ScheduledJobHandoff $handoff): JsonResponse
    {
        $this->authorizeManagement();
        $values = $request->validate([
            'fenced_endpoint' => ['required', 'string', 'max:2048'],
            'confirmed' => ['required', Rule::in([true, 1, '1'])],
        ]);

        try {
            $handoff = $this->resolver->forceHandoff($handoff, $values['fenced_endpoint'], session('user_uuid'));
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], $exception->getCode() === 409 ? 409 : 422);
        }

        return response()->json([
            'message' => __('Forced takeover recorded. Keep the previous server fenced until replication is repaired.'),
            'handoff' => $handoff,
            'active_node' => $this->resolver->statusContext(),
        ]);
    }

    public function rotateSecret(): JsonResponse
    {
        $this->authorizeManagement();
        try {
            $this->resolver->rotateCoordinationSecret();
        } catch (RuntimeException $exception) {
            return $this->managementError($exception);
        }

        return response()->json([
            'message' => __('Scheduled-job peer secret rotated. Wait for replication before discovering peers.'),
            'active_node' => $this->resolver->statusContext(),
        ]);
    }

    private function authorizeManagement(): void
    {
        abort_unless(isSuperAdmin(), 403);
    }

    private function managementError(RuntimeException $exception): JsonResponse
    {
        return response()->json(['message' => $exception->getMessage()], $exception->getCode() === 409 ? 409 : 422);
    }
}
