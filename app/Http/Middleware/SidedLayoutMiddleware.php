<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SidedLayoutMiddleware
{
    public function handle(Request $request, Closure $next, $layoutSide = 'left'): Response
    {
        $request->attributes->set('layoutSide', $layoutSide);
        
        return $next($request);
    }
}
