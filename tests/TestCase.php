<?php

namespace Tests;

use App\Models\Menu;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** 与 ProductionSeeder 的菜单字典保持一致 */
    public const MENU_KEYS = [
        'dashboard', 'user', 'attendance', 'shift', 'schedule', 'dutylog', 'calendar',
        'leave', 'leavebalance', 'room', 'accommodation', 'card', 'repair', 'safety',
        'volunteer', 'training', 'material', 'inbound', 'outbound', 'work',
        'assess', 'assess-appeal', 'peer-review',
    ];

    /** 建齐菜单字典（menu:xxx 中间件与工作台快速入口都依赖它） */
    protected function seedMenus(): void
    {
        foreach (self::MENU_KEYS as $i => $key) {
            Menu::updateOrCreate(['key' => $key], [
                'label' => $key,
                'sort' => $i + 1,
            ]);
        }
    }

    /** 给用户勾选菜单权限（自动先建齐菜单字典，幂等） */
    protected function grantMenus(User $user, string|array $keys): User
    {
        $this->seedMenus();
        $keys = is_string($keys) ? [$keys] : $keys;
        $menuIds = Menu::whereIn('key', $keys)->pluck('id');
        $user->menus()->syncWithoutDetaching($menuIds);

        return $user->refresh();
    }

    /** 管理员（勾全部菜单） */
    protected function adminUser(): User
    {
        $this->seedMenus();

        return $this->grantMenus(User::factory()->admin()->create(), self::MENU_KEYS);
    }

    /** 普通工作人员（默认无任何菜单权限） */
    protected function staffUser(): User
    {
        $this->seedMenus();

        return User::factory()->create();
    }
}
