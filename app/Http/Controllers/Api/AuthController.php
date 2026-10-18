<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    use ApiResponse;

    /** POST /api/login 登录（工号 + 密码，Session 认证——技术方案 §4.1） */
    public function login(LoginRequest $request)
    {
        $credentials = $request->only('employee_no', 'password');

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return $this->fail(1004, '工号或密码错误', 422);
        }

        $request->session()->regenerate();

        return $this->me($request);
    }

    /** POST /api/logout 登出 */
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return $this->ok(null, '已登出');
    }

    /** GET /api/me 当前用户 + 可见菜单 + 身份 */
    public function me(Request $request)
    {
        $user = $request->user()->load('menus');

        return $this->ok([
            'id' => $user->id,
            'username' => $user->username,
            'employee_no' => $user->employee_no,
            'phone' => $user->phone,
            'identity' => $user->identity->value,
            'identity_label' => $user->identity->label(),
            'is_admin' => $user->isAdmin(),
            // 报修模块字段级权限（需求 §6：维修情况仅管理员与维修负责人可改）
            'can_edit_repair_status' => $user->canEditRepairStatus(),
            'is_repair_follower' => $user->isRepairFollower(),
            'menus' => $user->menus->map(fn ($m) => [
                'key' => $m->key,
                'label' => $m->label,
                'icon' => $m->icon,
                'sort' => $m->sort,
            ])->sortBy('sort')->values(),
        ]);
    }
}
