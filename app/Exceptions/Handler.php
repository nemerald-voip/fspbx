<?php

namespace App\Exceptions;

use Throwable;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Illuminate\Http\Request;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        $this->reportable(function (Throwable $e) {
            //
        });

        $this->renderable(function (ApiException $e, Request $request) {
            if (! $request->is('api/v1/*')) {
                return null;
            }

            return response()->json([
                'error' => array_filter([
                    'type'    => $e->type,
                    'message' => $e->getMessage(),
                    'code'    => $e->code,
                    'param'   => $e->param,
                    'doc_url' => 'https://www.fspbx.com/docs/api/errors/',
                ], fn($v) => $v !== null && $v !== ''),
            ], $e->status);
        });
    }

    protected function unauthenticated($request, AuthenticationException $exception)
    {
        if ($request->is('api/*')) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        return redirect()->guest(route('login'));
    }


    public function render($request, Throwable $e)
    {
        if ($request->is('api/*') || $request->expectsJson()) {

            $status = $e instanceof HttpExceptionInterface
                ? $e->getStatusCode()
                : 500;

            $payload = [
                'success' => false,
                'message' => $status === 500 ? 'Server Error.' : ($e->getMessage() ?: 'Request failed.'),
            ];

            return response()->json($payload, $status);
        }

        return parent::render($request, $e);
    }
}
