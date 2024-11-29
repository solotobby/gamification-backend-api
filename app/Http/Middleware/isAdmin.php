<?php

namespace App\Http\Middleware;

use Auth;
use Closure;
use App\Models\User;

class isAdmin
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
            return response()->json(['status' => 'not ok', 'message' => 'Unauthorized Access Please Login'], 401);
        }
        $userid = auth()->user()->id;
        $getUserRole = User::where('id', $userid)->first();
        $adminRoles = 'admin';
        if ($getUserRole->role != $adminRoles){
            return response()->json(['status' => 'not ok', 'message' => 'This page is forbidden, Login as an administrator'], 403);
        }


        return $next($request);
    }
}
