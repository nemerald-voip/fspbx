<?php

namespace App\Http\Controllers\Auth;

use Throwable;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Password;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class ForgotPasswordController extends Controller
{
    public function sendResetLinkEmail(Request $request): JsonResponse
    {
        $validator = Validator::make(
            $request->all(),
            [
                'email' => ['required', 'email'],
            ],
            [
                'email.required' => 'Email is required.',
                'email.email' => 'Please enter a valid email address.',
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'messages' => [
                    'error' => ['Please correct the highlighted fields.'],
                ],
                'errors' => $validator->errors()->toArray(),
            ], 422);
        }

        try {
            $status = Password::broker()->sendResetLink([
                'user_email' => $request->email,
            ]);

            if ($status === Password::RESET_LINK_SENT) {
                return response()->json([
                    'messages' => [
                        'success' => [trans($status)],
                    ],
                ], 200);
            }

            if ($status === Password::INVALID_USER) {
                return response()->json([
                    'messages' => [
                        'error' => [trans($status)],
                    ],
                    'errors' => [
                        'email' => [trans($status)],
                    ],
                ], 422);
            }

            return response()->json([
                'messages' => [
                    'error' => [trans($status)],
                ],
            ], 422);
        } catch (TransportExceptionInterface $e) {
            logger('Forgot password mail transport error: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            $message = 'Unable to send reset email right now. Please try again later.';

            if (str_contains($e->getMessage(), 'Failed to authenticate on SMTP server')) {
                $message = 'Mail server authentication failed. Please check SMTP username and password.';
            } elseif (str_contains($e->getMessage(), 'Connection could not be established')) {
                $message = 'Could not connect to the mail server. Please check the mail host and port.';
            }

            return response()->json([
                'messages' => [
                    'error' => [$message],
                ],
            ], 503);
        } catch (Throwable $e) {
            logger('Forgot password error: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            $response = [
                'messages' => [
                    'error' => ['Something went wrong while sending the reset link.'],
                ],
            ];

            if (app()->environment('local') || config('app.debug')) {
                $response['debug'] = [
                    'type' => class_basename($e),
                    'message' => $e->getMessage(),
                ];
            }

            return response()->json($response, 500);
        }
    }
}
