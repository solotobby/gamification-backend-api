<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class isUser
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        // Ensure the user is authenticated
        if (!Auth::check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized Access. Please log in.',
            ], 401);
        }

        // Check if the user role is 'regular'
        $user = Auth::user();
        if ($user->role !== 'regular') {
            return response()->json([
                'status' => false,
                'message' => 'Access forbidden. Please log in as a regular user.',
            ], 403);
        }

        return $next($request);
    }
}
