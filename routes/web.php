<?php

use Illuminate\Support\Facades\Route;

// 命名 login 路由：未登录访问受保护资源时 Authenticate 中间件会生成跳转目标，
// 缺了它未登录调 API 会因 Route[login] not defined 变 500（API 由异常渲染器统一返回 401 JSON）
Route::get('/login', fn () => view('app'))->name('login');

// SPA 入口：除 /api 外的所有前端路由都交给 Vue Router（history 模式）
Route::get('/{any}', fn () => view('app'))
    ->where('any', '^(?!api|up).*$');
