<?php

namespace App\Http\Middleware;

use App\Services\Logging\ActivityLoggerService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class TrackActivity
{
    /**
     * Handle an incoming request and record activity telemetry.
     *
     * @param Request $request
     * @param Closure $next
     * @param string|null $activityType
     * @return Response
     */
    public function handle(Request $request, Closure $next, ?string $activityType = null): Response
    {
        $response = $next($request);

        // Record activity after response is prepared (or during terminable middleware)
        try {
            $user = $request->user('api') ?: $request->user();
            
            // Only track if user is authenticated or on specific named mutating actions
            if ($user || $request->isMethodSafe() === false) {
                $status = $response->getStatusCode();
                
                // Do not track 404 / 401 unauthenticated probe spam
                if ($status !== 404 && $status !== 401) {
                    $method = strtoupper($request->method());
                    $path = $request->path();
                    $routeName = $request->route() ? $request->route()->getName() : null;

                    $type = $activityType ?: ($user && ($user->role === 'admin' || $user->role === 'staff') ? 'admin_action' : 'api_request');
                    
                    $description = sprintf(
                        '%s %s by %s',
                        $method,
                        $routeName ?: $path,
                        $user ? $user->name : 'Guest'
                    );

                    $userType = $user && ($user->role === 'admin' || $user->role === 'staff') ? 'admin' : 'regular';

                    ActivityLoggerService::log(
                        $user,
                        $type,
                        $description,
                        $userType,
                        [
                            'method' => $method,
                            'path' => $path,
                            'route_name' => $routeName,
                            'status_code' => $status,
                        ],
                        $request,
                        "{$method} /{$path}"
                    );
                }
            }
        } catch (Throwable $e) {
            // Silently ignore tracking errors to protect primary response flow
        }

        return $response;
    }
}
