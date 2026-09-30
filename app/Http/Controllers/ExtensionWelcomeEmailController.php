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
                'messages' => ['error' => [__('No welcome emails are eligible to send.')]],
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
                    trans_choice('{1} :count welcome email queued successfully.|[0,*] :count welcome emails queued successfully.', $queued),
                    ...($skipped > 0
                        ? [trans_choice('{1} :count selected extension was skipped.|[0,*] :count selected extensions were skipped.', $skipped)]
                        : []),
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
