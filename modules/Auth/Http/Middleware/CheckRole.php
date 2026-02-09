<?php

namespace Modules\Auth\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        if (! $request->user() || ! $request->user()->roles->contains('name', $role)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized action.'
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
