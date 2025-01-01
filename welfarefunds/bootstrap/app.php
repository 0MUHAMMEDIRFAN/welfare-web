<?php  

use Illuminate\Foundation\Application;  
use Illuminate\Foundation\Configuration\Exceptions;  
use Illuminate\Foundation\Configuration\Middleware;  
use App\Http\Middleware\EnsureJsonResponse;  
use App\Providers\RouteServiceProvider;  

return Application::configure(basePath: dirname(__DIR__))  
    ->withRouting(  
        web: __DIR__.'/../routes/web.php',  
        api: __DIR__.'/../routes/api.php',  
        commands: __DIR__.'/../routes/console.php',  
        health: '/up',  
    )  
    ->withProviders([  
        RouteServiceProvider::class,  // Register the provider  
    ])  
    ->withMiddleware(function (Middleware $middleware) {  
        // Global middleware  
        $middleware->use([  
            \Illuminate\Http\Middleware\HandleCors::class,  
            \Illuminate\Cookie\Middleware\EncryptCookies::class,  
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,  
            \Illuminate\Session\Middleware\StartSession::class,  
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,  
            \Illuminate\Foundation\Http\Middleware\ValidatePostSize::class,  
            \Illuminate\Foundation\Http\Middleware\TrimStrings::class,  
            \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,  
        ]);  
        
        // API middleware group  
        $middleware->group('api', [  
            EnsureJsonResponse::class,  
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,  
            'throttle:api',  
            \Illuminate\Routing\Middleware\SubstituteBindings::class,  
        ]);  

        // Web middleware group  
        $middleware->group('web', [  
            \Illuminate\Http\Middleware\HandleCors::class,  
            \Illuminate\Cookie\Middleware\EncryptCookies::class,  
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,  
            \Illuminate\Session\Middleware\StartSession::class,  
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,  
            \Illuminate\Foundation\Http\Middleware\ValidatePostSize::class,  
            \Illuminate\Foundation\Http\Middleware\TrimStrings::class,  
            \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,  
        ]);  

        // Add middleware aliases  
        $middleware->alias([  
            'auth.sanctum' => \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,  
            'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,  
        ]);  
    })  
    ->withExceptions(function (Exceptions $exceptions) {  
        //  
    })->create(); 