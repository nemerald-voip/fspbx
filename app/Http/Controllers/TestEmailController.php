<?php

namespace App\Http\Controllers;

use App\Mail\TestEmail;
use App\Models\EmailLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class TestEmailController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        if (! userCheckPermission('email_test_send')) {
            return response()->json([
                'messages' => ['error' => ['Permission denied.']],
            ], 403);
        }

        $validated = $request->validate([
            'email' => ['required', 'email:rfc', 'max:255'],
        ]);

        try {
            $logId = (string) Str::uuid();

            Mail::to($validated['email'])->send(new TestEmail([
                'domain_uuid' => session('domain_uuid'),
                'email_subject' => 'Test email',
                'logId' => $logId,
                'sent_at' => now()->toDateTimeString(),
            ]));

            return response()->json([
                'messages' => ['success' => ['Test email sent.']],
            ]);
        } catch (\Throwable $exception) {
            if (isset($logId)) {
                try {
                    EmailLog::query()
                        ->where('uuid', $logId)
                        ->update([
                            'status' => 'failed',
                            'sent_debug_info' => 'Test email failed at ' . now()->toDateTimeString() . ': ' . $exception->getMessage(),
                        ]);
                } catch (\Throwable $logException) {
                    logger('TestEmailController@store log update error: ' . $logException->getMessage());
                }
            }

            logger('TestEmailController@store error: ' . $exception->getMessage() . ' at ' . $exception->getFile() . ':' . $exception->getLine());

            return response()->json([
                'messages' => ['error' => ['Unable to send the test email. Check the email logs for details.']],
            ], 500);
        }
    }
}
