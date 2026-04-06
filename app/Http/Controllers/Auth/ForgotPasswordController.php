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

        $previousSocketTimeout = ini_get('default_socket_timeout');
        ini_set('default_socket_timeout', '10');

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

            $exceptionMessage = $e->getMessage();
            $message = 'Unable to send reset email right now. Please try again later.';

            if (str_contains($exceptionMessage, 'Failed to authenticate on SMTP server')) {
                $message = 'Mail server authentication failed. Please check SMTP username and password.';
            } elseif (
                str_contains($exceptionMessage, 'Connection could not be established') ||
                str_contains($exceptionMessage, 'Connection refused')
            ) {
                $message = 'Could not connect to the mail server. Please check the mail host and port.';
            } elseif (str_contains($exceptionMessage, 'Connection timed out')) {
                $message = 'Could not connect to the mail server. Please check the mail host, port, or firewall settings.';
            } elseif (
                str_contains($exceptionMessage, 'php_network_getaddresses') ||
                str_contains($exceptionMessage, 'getaddrinfo failed') ||
                str_contains($exceptionMessage, 'Name or service not known') ||
                str_contains($exceptionMessage, 'nodename nor servname provided')
            ) {
                $message = 'Mail server hostname could not be resolved. Please check the mail host setting.';
            } elseif (
                str_contains($exceptionMessage, 'stream_socket_enable_crypto') ||
                str_contains($exceptionMessage, 'SSL') ||
                str_contains($exceptionMessage, 'TLS') ||
                str_contains($exceptionMessage, 'certificate')
            ) {
                $message = 'Secure connection to the mail server failed. Please check the encryption and port settings.';
            } elseif (
                str_contains($exceptionMessage, 'relay access denied') ||
                str_contains($exceptionMessage, 'not permitted to relay') ||
                str_contains($exceptionMessage, 'Expected response code "550"')
            ) {
                $message = 'Mail server rejected the message. Please check the sending address and SMTP account permissions.';
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
        } finally {
        ini_set('default_socket_timeout', (string) $previousSocketTimeout);
    }
    }
}
