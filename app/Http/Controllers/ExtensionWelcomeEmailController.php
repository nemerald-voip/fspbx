<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExtensionWelcomeEmailOptionsRequest;
use App\Http\Requests\SendExtensionWelcomeEmailRequest;
use App\Jobs\SendExtensionWelcomeEmail;
use App\Services\ExtensionWelcomeEmailService;
use Illuminate\Http\JsonResponse;

class ExtensionWelcomeEmailController extends Controller
{
    public function options(
        ExtensionWelcomeEmailOptionsRequest $request,
        ExtensionWelcomeEmailService $service
    ): JsonResponse {
        return response()->json(
            $service->options($request->validated('items'), session('domain_uuid'))
        );
    }

    public function send(
        SendExtensionWelcomeEmailRequest $request,
        ExtensionWelcomeEmailService $service
    ): JsonResponse {
        $data = $request->validated();
        $domainUuid = (string) session('domain_uuid');
        $result = $service->options(
            $data['items'],
            $domainUuid,
            $data['recipient'] ?? null
        );

        $eligible = collect($result['items'])->where('eligible', true);

        if ($eligible->isEmpty()) {
            return response()->json([
                'messages' => ['error' => ['No welcome emails are eligible to send.']],
                ...$result,
            ], 422);
        }

        foreach ($eligible as $item) {
            SendExtensionWelcomeEmail::dispatch(
                $item['extension_uuid'],
                $domainUuid,
                $item['recipient']
            );
        }

        $queued = $eligible->count();
        $skipped = $result['summary']['skipped'];

        return response()->json([
            'messages' => [
                'success' => [
                    $queued === 1
                        ? 'Welcome email queued successfully.'
                        : "{$queued} welcome emails queued successfully.",
                    ...($skipped > 0 ? ["{$skipped} selected extension(s) were skipped."] : []),
                ],
            ],
            'summary' => [
                ...$result['summary'],
                'queued' => $queued,
            ],
            'items' => $result['items'],
        ]);
    }
}
