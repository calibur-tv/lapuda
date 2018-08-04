<?php

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    /**
     * The application's global HTTP middleware stack.
     *
     * These middleware are run during every request to your application.
     *
     * @var array
     */
    protected $middleware = [];

    /**
     * The application's route middleware groups.
     *
     * @var array
     */
    protected $middlewareGroups = [
        'web' => [
            \Barryvdh\Cors\HandleCors::class,
            \App\Http\Middleware\Csrf::class
        ],
        'api' => [
            \Barryvdh\Cors\HandleCors::class,
            \App\Http\Middleware\Csrf::class
        ]
    ];

    /**
     * The application's route middleware.
     *
     * These middleware may be assigned to groups or used individually.
     *
     * @var array
     */
    protected $routeMiddleware = [
        'bindings' => \Illuminate\Routing\Middleware\SubstituteBindings::class,
        'can' => \Illuminate\Auth\Middleware\Authorize::class,
        'throttle' => \App\Http\Middleware\Throttle::class,
        'jwt.auth' => \App\Http\Middleware\Auth::class,
        'jwt.admin' => \App\Http\Middleware\Admin::class,
        'jwt.refresh' => \App\Http\Middleware\Refresh::class,
        'geetest' => \App\Http\Middleware\Geetest::class
    ];
}
