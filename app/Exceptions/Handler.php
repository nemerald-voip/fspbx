<?php

namespace App\Exceptions;

use Throwable;
use Illuminate\Http\Request;
use App\Data\Api\V1\ErrorData;
use App\Data\Api\V1\ErrorResponseData;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Validation\ValidationException;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;

class Handler extends ExceptionHandler
{
    public function register(): void
    {
        // 1) Validation (FormRequest / validator)
        $this->renderable(function (ValidationException $e, Request $request) {
            if (! $request->is('api/v1/*')) return null;

            $errors = $e->errors();
            $param = array_key_first($errors);
            $message = $param ? ($errors[$param][0] ?? 'Invalid request.') : 'Invalid request.';

            $payload = ErrorResponseData::from([
                'error' => ErrorData::from([
                    'type'    => 'invalid_request_error',
                    'message' => $message,
                    'code'    => 'invalid_parameter',
                    'param'   => $param,
                    'doc_url' => 'https://www.fspbx.com/docs/api/v1/errors/',
                ]),
            ]);

            return response()->json($payload->toArray(), 400);
        });

        // 2) Authorization (policies/gates OR FormRequest->authorize() = false)
        $this->renderable(function (AuthorizationException $e, Request $request) {
            if (! $request->is('api/v1/*')) return null;

            $payload = ErrorResponseData::from([
                'error' => ErrorData::from([
                    'type'    => 'invalid_request_error',
                    'message' => $e->getMessage() ?: 'Forbidden.',
                    'code'    => 'forbidden',
                    'doc_url' => 'https://www.fspbx.com/docs/api/v1/errors/',
                ]),
            ]);

            return response()->json($payload->toArray(), 403);
        });

        // 3) Authentication (if something throws AuthenticationException)
        $this->renderable(function (AuthenticationException $e, Request $request) {
            if (! $request->is('api/v1/*')) return null;

            $payload = ErrorResponseData::from([
                'error' => ErrorData::from([
                    'type'    => 'authentication_error',
                    'message' => 'Unauthenticated.',
                    'code'    => 'unauthenticated',
                    'doc_url' => 'https://www.fspbx.com/docs/api/v1/errors/',
                ]),
            ]);

            return response()->json($payload->toArray(), 401);
        });

        // 4) Method (if something throws MethodNotAllowedHttpException)
        $this->renderable(function (MethodNotAllowedHttpException $e, Request $request) {
            if (! $request->is('api/v1/*')) return null;

            $payload = ErrorResponseData::from([
                'error' => ErrorData::from([
                    'type'    => 'forbidden_method',
                    'message' => $e->getMessage(),
                    'code'    => 'forbidden',
                    'doc_url' => 'https://www.fspbx.com/docs/api/v1/errors/',
                ]),
            ]);

            return response()->json($payload->toArray(), 401);
        });

        $this->renderable(function (ModelNotFoundException $e, Request $request) {
            if (! $request->is('api/v1/*')) {
                return null;
            }

            // Friendly resource name (optional)
            $resource = class_basename($e->getModel() ?? '') ?: 'Resource';

            $payload = ErrorResponseData::from([
                'error' => ErrorData::from([
                    'type'    => 'invalid_request_error',
                    'message' => "{$resource} not found.",
                    'code'    => 'resource_missing',
                    'param'   => '',
                    'doc_url' => 'https://www.fspbx.com/docs/api/v1/errors/',
                ]),
            ]);

            return response()->json($payload->toArray(), 404);
        });

        $this->renderable(function (NotFoundHttpException $e, Request $request) {
            if (! $request->is('api/v1/*')) {
                return null;
            }

            $payload = ErrorResponseData::from([
                'error' => ErrorData::from([
                    'type'    => 'not_found',
                    'message' => "Resource not found.",
                    'code'    => 'resource_not_found',
                    'param'   => '',
                    'doc_url' => 'https://www.fspbx.com/docs/api/v1/errors/',
                ]),
            ]);

            return response()->json($payload->toArray(), 404);
        });

        // 5) Your custom ApiException
        $this->renderable(function (ApiException $e, Request $request) {
            if (! $request->is('api/v1/*')) return null;

            $payload = ErrorResponseData::from([
                'error' => ErrorData::from([
                    'type'    => $e->type,
                    'message' => $e->getMessage(),
                    'code'    => $e->error_code,
                    'param'   => $e->param,
                    'doc_url' => 'https://www.fspbx.com/docs/api/v1/errors/',
                ]),
            ]);

            return response()->json($payload->toArray(), $e->status);
        });

        // 5) Catch-all MUST be last
        $this->renderable(function (Throwable $e, Request $request) {
            if (! $request->is('api/v1/*')) return null;

            logger()->error('Unhandled API exception', ['exception' => $e]);

            $payload = ErrorResponseData::from([
                'error' => ErrorData::from([
                    'type'    => 'api_error',
                    'message' => 'An unexpected error occurred.',
                    'doc_url' => 'https://www.fspbx.com/docs/api/v1/errors/',
                ]),
            ]);

            return response()->json($payload->toArray(), 500);
        });
    }

    // Optional: you can delete this override entirely once the AuthenticationException
    // renderable is in place. If you keep it, make it Stripe-like for v1:
    protected function unauthenticated($request, AuthenticationException $exception)
    {
        if ($request->is('api/v1/*')) {
            return response()->json([
                'error' => [
                    'type' => 'authentication_error',
                    'message' => 'Unauthenticated.',
                    'code' => 'unauthenticated',
                ],
            ], 401);
        }

        return redirect()->guest(route('login'));
    }
}
