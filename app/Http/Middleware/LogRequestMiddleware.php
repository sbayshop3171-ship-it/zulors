<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogRequestMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if(config('logging.log_requests')) {
            requests_log('Incoming request', [
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'referer' => $request->headers->get('referer'),
                'input' => $request->all(),
                'headers' => $request->headers->all(),
                'cookies' => $request->cookies->all(),
                'session' => $request->session()->all(),
                'user' => $request->user() ? [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                ] : null,
                'created_at' => now()->toDateTimeString(),
            ]);
        }

        return $next($request);
    }
}
