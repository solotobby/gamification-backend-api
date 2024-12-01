<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/home';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        $this->routes(function () {
            // Dynamically load all route files from 'routes/Admin'
            foreach (glob(base_path('routes/Admin/*.php')) as $routeFile) {
                Route::middleware('api') // Use 'web' middleware if required
                    ->prefix('api') // Adjust the prefix based on your needs
                    ->group($routeFile);
            }

            // Dynamically load all route files from 'routes/Auth'
            foreach (glob(base_path('routes/Auth/*.php')) as $routeFile) {
                Route::middleware('api') // Use 'web' middleware if required
                    ->prefix('api') // Adjust the prefix based on your needs
                    ->group($routeFile);
            }

            // Dynamically load all route files from 'routes/Public'
            foreach (glob(base_path('routes/Public/*.php')) as $routeFile) {
                Route::middleware('api') // Or 'web'
                    ->prefix('api')
                    ->group($routeFile);
            }

            // Dynamically load all route files from 'routes/User'
            foreach (glob(base_path('routes/User/*.php')) as $routeFile) {
                Route::middleware('api') // Or 'web'
                    ->prefix('api')
                    ->group($routeFile);
            }

            // Load individual utility route files explicitly if needed
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }
}
