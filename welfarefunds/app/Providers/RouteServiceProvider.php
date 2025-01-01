<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;  
use Illuminate\Http\Request; 
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\RateLimiter; 
use Illuminate\Support\Facades\Route; 

class RouteServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void  
    {  
        RateLimiter::for('api', function (Request $request) {  
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());  
        });  

        // Define API routes  
        Route::middleware('api')  
            ->prefix('api')  
            ->group(base_path('routes/api.php'));  

        // Define web routes  
        Route::middleware('web')  
            ->group(base_path('routes/web.php'));  
    }  
}
