<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserStoreRequest;
use App\Http\Requests\UserUpdateRequest;
use App\Models\Menu;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    use ApiResponse;

    /**
     * GET /api/users/options 人员字典（下拉用）
     * 这是跨模块公共字典（值班日志 / 排班 / 考勤 / 假勤都要选人），
     * 只校验登录，不校验「用户管理」菜单权限——否则没勾「用户管理」的人
     * 打开其他模块会被误报无权限。
     */
    public function options()
    {
        $users = User::orderBy('id')
            ->get(['id', 'username', 'employee_no', 'identity'])
            ->map(fn ($u) => [
                'id' => $u->id,
                'username' => $u->username,
                'employee_no' => $u->employee_no,
                'identity' => $u->identity->value,
            ]);

        return $this->ok($users);
    }

    /** GET /api/users 用户列表（搜索：姓名/工号关键字 + 身份筛选） */
    public function index(Request $request)
    {
        $query = User::with('menus:id,key,label,sort');

        if ($kw = trim((string) $request->input('keyword', ''))) {
            $query->where(function ($q) use ($kw) {
                $q->where('username', 'like', "%{$kw}%")
                    ->orWhere('employee_no', 'like', "%{$kw}%");
            });
        }

        if ($identity = $request->input('identity')) {
            $query->where('identity', $identity);
        }

        $paginator = $query->orderBy('employee_no')
            ->paginate((int) $request->input('pageSize', 10));

        $paginator->setCollection($paginator->getCollection()->map(fn ($u) => $this->toRow($u)));

        return $this->page($paginator);
    }

    /** POST /api/users 新增用户（含权限勾选，事务） */
    public function store(UserStoreRequest $request)
    {
        $data = $request->validated();

        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'username' => $data['username'],
                'employee_no' => $data['employee_no'],
                'phone' => $data['phone'] ?? null,
                'identity' => $data['identity'],
                'password' => $data['password'],
            ]);
            $user->menus()->sync($this->menuIdsByKeys($data['menu_keys']));

            return $user;
        });

        return $this->ok($this->toRow($user->load('menus')), '已添加');
    }

    /** PUT /api/users/{id} 编辑用户（密码留空则不修改） */
    public function update(UserUpdateRequest $request, User $user)
    {
        $data = $request->validated();

        DB::transaction(function () use ($user, $data) {
            $user->fill([
                'username' => $data['username'],
                'employee_no' => $data['employee_no'],
                'phone' => $data['phone'] ?? null,
                'identity' => $data['identity'],
            ]);
            if (! empty($data['password'])) {
                $user->password = $data['password'];
            }
            $user->save();
            $user->menus()->sync($this->menuIdsByKeys($data['menu_keys']));
        });

        return $this->ok($this->toRow($user->fresh()->load('menus')), '已保存');
    }

    /** DELETE /api/users/{id} 删除（软删除；不允许删除自己） */
    public function destroy(Request $request, User $user)
    {
        if ($request->user()->id === $user->id) {
            return $this->fail(3001, '不能删除当前登录账号', 422);
        }

        $user->delete();

        return $this->ok(null, '已删除');
    }

    /** 列表行结构（与原型用户管理列一致） */
    private function toRow(User $u): array
    {
        return [
            'id' => $u->id,
            'username' => $u->username,
            'employee_no' => $u->employee_no,
            'phone' => $u->phone,
            'identity' => $u->identity->value,
            'identity_label' => $u->identity->label(),
            'menu_keys' => $u->menus->pluck('key')->all(),
            'menu_labels' => $u->menus->sortBy('sort')->pluck('label')->values()->all(),
            'created_at' => $u->created_at?->format('Y-m-d H:i'),
            'updated_at' => $u->updated_at?->format('Y-m-d H:i'),
        ];
    }

    /** key → id（非法 key 直接忽略，校验层已挡） */
    private function menuIdsByKeys(array $keys): array
    {
        return Menu::whereIn('key', $keys)->pluck('id')->all();
    }
}
