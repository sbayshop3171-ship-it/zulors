<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminRoleMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! auth_check() || ! (me()->isAdmin() || me()->isRoot())) {
            return redirect()->route('admin.auth.login');
        }

        return $next($request);
    }
}
