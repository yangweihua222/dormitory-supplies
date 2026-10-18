<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 菜单权限校验（技术方案 §4.2）：当前用户必须勾选了指定菜单 key。
 * 用法：->middleware('menu:user')
 */
class MenuPermission
{
    public function handle(Request $request, Closure $next, string $key): Response
    {
        if (! $request->user()->hasMenu($key)) {
            return response()->json([
                'code' => 1002,
                'message' => '无该菜单权限',
                'data' => null,
            ], 403);
        }

        return $next($request);
    }
}
