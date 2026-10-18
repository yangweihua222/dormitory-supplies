<?php

use App\Http\Middleware\MenuPermission;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // API 挂在 web 中间件组下：同域 Session 认证 + CSRF（技术方案 §二，不引入 Sanctum）
            Route::middleware('web')
                ->prefix('api')
                ->group(base_path('routes/api.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'menu' => MenuPermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // API 统一 JSON 错误（技术方案 §4.3/§4.4）
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['code' => 1001, 'message' => '未登录或登录已过期', 'data' => null], 401);
            }
        });
        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'code' => 2001,
                    'message' => $e->validator->errors()->first(),
                    'data' => ['errors' => $e->errors()],
                ], 422);
            }
        });
    })->create();
