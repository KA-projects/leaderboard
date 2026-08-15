<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PrettyJson
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->query('pretty') !== '1') {
            return $response;
        }

        $decoded = json_decode($response->getContent());

        if (json_last_error() !== JSON_ERROR_NONE) {
            return $response;
        }

        $response->setContent(
            json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        );

        return $response;
    }
}
