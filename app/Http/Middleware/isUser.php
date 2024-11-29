<?php

namespace App\Http\Middleware;

use Auth;
use Closure;
use App\Models\User;

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
        if (!Auth::check()) {
            return response()->json(['status' => false, 'message' => 'Unauthorized Access Please Login'], 401);
        }
        $userid = auth()->user()->id;
        $getUserRole = User::where('id', $userid)->first();
        $userRoles = 'regular';
        if ($getUserRole->role != $userRoles){
            return response()->json(['status' => false, 'message' => 'This page is forbidden, Login as a user'], 403);
        }


        return $next($request);
    }
}
