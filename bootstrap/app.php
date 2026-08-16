<?php

use App\Http\Middleware\PrettyJson;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(append: [
            PrettyJson::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (ValidationException $e, Request $request) {
            if (! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], $e->status, [], JSON_UNESCAPED_UNICODE);
        });

        $exceptions->render(function (NotFoundHttpException $e) {
            $message = $e->getPrevious() instanceof ModelNotFoundException
                ? 'Пользователь не найден.'
                : 'Маршрут не найден.';

            return response()->json(['message' => $message], 404, [], JSON_UNESCAPED_UNICODE);
        });

        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->expectsJson()) {
                return null;
            }

            $status = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;

            $message = match (true) {
                $status >= 500 => 'Внутренняя ошибка сервера.',
                $status === 405 => 'Метод не поддерживается.',
                $status === 422 => 'Ошибка валидации.',
                default => 'Ошибка запроса.',
            };

            $data = ['message' => $message];

            if (config('app.debug')) {
                $data['exception'] = $e::class;
                $data['file'] = $e->getFile();
                $data['line'] = $e->getLine();
                $data['trace'] = $e->getTrace();
            }

            return response()->json($data, $status, [], JSON_UNESCAPED_UNICODE);
        });
    })->create();
