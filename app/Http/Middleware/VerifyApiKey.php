<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Traits\Http\Api\SupportsApiResponses;
use Symfony\Component\HttpFoundation\Response;

class VerifyApiKey
{
    use SupportsApiResponses;

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->header('X-API-KEY') !== config('app.api_key')) {
            return $this->responseUnauthorizedError([
                'message' => 'API key is invalid or not provided.'
            ]);
        }

        return $next($request);
    }
}
