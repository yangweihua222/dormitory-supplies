<?php

namespace Database\Seeders;

use App\Enums\Identity;
use App\Enums\ShiftType;
use App\Models\Menu;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * 生产环境种子（甲方部署用）——只含系统运行的最小基础数据：
 *   1. 菜单字典（侧边栏 + 权限分配的数据源）
 *   2. 管理员账号（工号 1001 / 初始密码 123456，首次登录后应立即修改）
 *   3. 四个标准班次（早班/午班/晚班/值班，需求文档 §4）
 *
 * 不含任何演示业务数据：人员、房间、排班、待办、物资等全部由甲方在系统内自行录入。
 * 用法：php artisan db:seed --class=ProductionSeeder
 */
class ProductionSeeder extends Seeder
{
    /** 菜单（与开发版 DatabaseSeeder::MENUS 保持一致） */
    private const MENUS = [
        ['key' => 'dashboard',     'label' => '工作台',       'icon' => 'dashboard', 'sort' => 1],
        ['key' => 'user',          'label' => '用户管理',     'icon' => 'users',     'sort' => 2],
        ['key' => 'attendance',    'label' => '考勤统计',     'icon' => 'clock',     'sort' => 3],
        ['key' => 'shift',         'label' => '班次管理',     'icon' => 'shift',     'sort' => 4],
        ['key' => 'schedule',      'label' => '排班管理',     'icon' => 'schedule',  'sort' => 5],
        ['key' => 'dutylog',       'label' => '值班日志',     'icon' => 'duty',      'sort' => 6],
        ['key' => 'calendar',      'label' => '排班日历',     'icon' => 'calendar',  'sort' => 7],
        ['key' => 'leave',         'label' => '请假列表',     'icon' => 'leave',     'sort' => 8],
        ['key' => 'leavebalance',  'label' => '假勤管理',     'icon' => 'calendar',  'sort' => 9],
        ['key' => 'room',          'label' => '房源管理',     'icon' => 'bed',       'sort' => 10],
        ['key' => 'accommodation', 'label' => '住宿管理',     'icon' => 'home',      'sort' => 11],
        ['key' => 'card',          'label' => '房卡管理',     'icon' => 'card',      'sort' => 12],
        ['key' => 'repair',        'label' => '报修管理',     'icon' => 'tool',      'sort' => 13],
        ['key' => 'safety',        'label' => '检查列表',     'icon' => 'shield',    'sort' => 14],
        ['key' => 'volunteer',     'label' => '消防志愿服务', 'icon' => 'users',     'sort' => 15],
        ['key' => 'training',      'label' => '消防培训',     'icon' => 'list',      'sort' => 16],
        ['key' => 'material',      'label' => '物资列表',     'icon' => 'box',       'sort' => 17],
        ['key' => 'inbound',       'label' => '入库管理',     'icon' => 'download',  'sort' => 18],
        ['key' => 'outbound',      'label' => '出库管理',     'icon' => 'upload',    'sort' => 19],
        ['key' => 'assess',        'label' => '月度考核',     'icon' => 'list',      'sort' => 20],
        ['key' => 'assess-appeal', 'label' => '考核申诉',     'icon' => 'edit',      'sort' => 21],
        ['key' => 'peer-review',   'label' => '同行评议',     'icon' => 'users',     'sort' => 22],
        ['key' => 'work',          'label' => '工作管理',     'icon' => 'chart',     'sort' => 23],
    ];

    public function run(): void
    {
        // 1. 菜单字典
        $menuIds = [];
        foreach (self::MENUS as $m) {
            $menu = Menu::updateOrCreate(['key' => $m['key']], $m);
            $menuIds[$m['key']] = $menu->id;
        }

        // 2. 管理员账号（全部菜单权限）
        $admin = User::updateOrCreate(
            ['employee_no' => '1001'],
            [
                'username' => '系统管理员',
                'phone' => null,
                'identity' => Identity::Admin,
                'password' => Hash::make('123456'),
            ]
        );
        $admin->menus()->sync(array_values($menuIds));

        // 3. 标准班次（需求文档 §4）
        $shifts = [
            ['name' => '早班', 'check_in_time' => '08:00:00', 'check_out_time' => '12:00:00', 'type' => ShiftType::NORMAL],
            ['name' => '午班', 'check_in_time' => '12:00:00', 'check_out_time' => '18:00:00', 'type' => ShiftType::NORMAL],
            ['name' => '晚班', 'check_in_time' => '18:00:00', 'check_out_time' => '22:00:00', 'type' => ShiftType::NORMAL],
            ['name' => '值班', 'check_in_time' => '22:00:00', 'check_out_time' => '08:00:00', 'type' => ShiftType::DUTY],
        ];
        foreach ($shifts as $s) {
            Shift::updateOrCreate(['name' => $s['name']], $s);
        }
    }
}
