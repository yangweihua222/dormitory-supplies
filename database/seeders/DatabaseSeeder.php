<?php

namespace Database\Seeders;

use App\Enums\ApproveStatus;
use App\Enums\CheckResult;
use App\Enums\EmergencyCategory;
use App\Enums\Identity;
use App\Enums\LeaveType;
use App\Enums\SafetyCategory;
use App\Enums\ScheduleStatus;
use App\Enums\ShiftType;
use App\Enums\TodoStatus;
use App\Enums\TodoType;
use App\Models\AttendanceRecord;
use App\Models\FireTraining;
use App\Models\FireVolunteer;
use App\Models\Leave;
use App\Models\Material;
use App\Models\MaterialStock;
use App\Models\Menu;
use App\Models\Room;
use App\Models\SafetyCheck;
use App\Models\Schedule;
use App\Models\Shift;
use App\Models\Todo;
use App\Models\TodoAssignee;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * 菜单（key 与原型侧边栏 page id 一致，数据库设计 §三.2）
     * 排序 = 原型侧边栏顺序（需求文档 §四）
     */
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

    /**
     * 示例人员（需求文档 §3.2，与原型 data.js 一致；初始密码统一 123456——技术方案 §4.1）
     * 权限为需求 §3.2 的菜单分配 + dashboard（工作台为登录后首页，全员可见）。
     * 「考勤管理」= attendance + shift；「安全管理」= safety + volunteer + training；
     * 「物资管理」= material + inbound + outbound。
     */
    private const USERS = [
        ['username' => '张明', 'employee_no' => '1001', 'identity' => Identity::Admin,
            'menus' => '*'],
        ['username' => '李静', 'employee_no' => '1002', 'identity' => Identity::Supervisor,
            'menus' => ['dashboard', 'accommodation', 'repair', 'card', 'attendance', 'shift', 'dutylog', 'safety', 'volunteer', 'training', 'assess', 'assess-appeal', 'peer-review']],
        ['username' => '王芳', 'employee_no' => '1003', 'identity' => Identity::Staff,
            'menus' => ['dashboard', 'accommodation', 'repair', 'card', 'attendance', 'shift', 'dutylog', 'safety', 'volunteer', 'training', 'assess', 'assess-appeal', 'peer-review']],
        ['username' => '陈晓',   'employee_no' => '1004', 'identity' => Identity::Staff,
            'menus' => ['dashboard', 'accommodation', 'repair', 'card', 'attendance', 'shift', 'dutylog', 'safety', 'volunteer', 'training', 'assess', 'assess-appeal', 'peer-review']],
        ['username' => '刘洋',   'employee_no' => '1005', 'identity' => Identity::Staff,
            'menus' => ['dashboard', 'accommodation', 'repair', 'card', 'attendance', 'shift', 'dutylog', 'safety', 'volunteer', 'training', 'assess', 'assess-appeal', 'peer-review']],
        ['username' => '赵磊',   'employee_no' => '1006', 'identity' => Identity::Staff,
            'menus' => ['dashboard', 'accommodation', 'repair', 'card', 'attendance', 'shift', 'dutylog', 'safety', 'volunteer', 'training', 'assess', 'assess-appeal', 'peer-review']],
        ['username' => '孙悦', 'employee_no' => '1007', 'identity' => Identity::Staff,
            'menus' => ['dashboard', 'accommodation', 'repair', 'card', 'attendance', 'shift', 'dutylog', 'safety', 'volunteer', 'training', 'assess', 'assess-appeal', 'peer-review']],
        ['username' => '周涛', 'employee_no' => '1008', 'identity' => Identity::Staff,
            'menus' => ['dashboard', 'attendance', 'shift', 'material', 'inbound', 'outbound', 'assess', 'assess-appeal', 'peer-review']],
    ];

    public function run(): void
    {
        $menuIds = [];
        foreach (self::MENUS as $m) {
            $menu = Menu::updateOrCreate(['key' => $m['key']], $m);
            $menuIds[$m['key']] = $menu->id;
        }

        foreach (self::USERS as $i => $u) {
            $user = User::updateOrCreate(
                ['employee_no' => $u['employee_no']],
                [
                    'username' => $u['username'],
                    'phone' => '1380000'.(1001 + $i),
                    'identity' => $u['identity'],
                    'password' => Hash::make('123456'),
                ]
            );

            $keys = $u['menus'] === '*' ? array_keys($menuIds) : $u['menus'];
            $user->menus()->sync(array_map(fn ($k) => $menuIds[$k], $keys));
        }

        $this->seedShiftsAndSchedules();
        $this->seedLeaves();
        $this->seedTodos();
        $this->seedSafety();
    }

    private function seedShiftsAndSchedules(): void
    {
        $shifts = [
            ['name' => '早班', 'check_in_time' => '08:00:00', 'check_out_time' => '12:00:00', 'type' => ShiftType::NORMAL],
            ['name' => '午班', 'check_in_time' => '12:00:00', 'check_out_time' => '18:00:00', 'type' => ShiftType::NORMAL],
            ['name' => '晚班', 'check_in_time' => '18:00:00', 'check_out_time' => '22:00:00', 'type' => ShiftType::NORMAL],
            ['name' => '值班', 'check_in_time' => '22:00:00', 'check_out_time' => '08:00:00', 'type' => ShiftType::DUTY],
        ];
        foreach ($shifts as $s) {
            Shift::updateOrCreate(['name' => $s['name']], $s);
        }

        $users = User::all()->keyBy('employee_no');
        $shiftMap = Shift::all()->keyBy('name');
        $today = Carbon::today();

        $assignments = [
            ['date' => $today, 'employee_no' => '1001', 'shift' => '早班', 'status' => ScheduleStatus::NORMAL],
            ['date' => $today, 'employee_no' => '1002', 'shift' => '早班', 'status' => ScheduleStatus::NORMAL],
            ['date' => $today, 'employee_no' => '1003', 'shift' => '午班', 'status' => ScheduleStatus::NORMAL],
            ['date' => $today, 'employee_no' => '1004', 'shift' => '晚班', 'status' => ScheduleStatus::NORMAL],
            ['date' => $today, 'employee_no' => '1005', 'shift' => '值班', 'status' => ScheduleStatus::NORMAL],
            ['date' => $today, 'employee_no' => '1006', 'shift' => '值班', 'status' => ScheduleStatus::NORMAL],
        ];

        foreach ($assignments as $a) {
            $user = $users[$a['employee_no']] ?? null;
            $shift = $shiftMap[$a['shift']] ?? null;
            if (! $user || ! $shift) {
                continue;
            }
            Schedule::updateOrCreate(
                ['user_id' => $user->id, 'date' => $a['date'], 'shift_id' => $shift->id],
                ['status' => $a['status'], 'leave_id' => null]
            );
        }

        // 历史考勤记录（用于统计演示）
        $yesterday = Carbon::yesterday();
        $records = [
            ['date' => $yesterday, 'employee_no' => '1001', 'shift' => '早班', 'in' => '08:00:12', 'out' => '12:05:30', 'late' => 0, 'early' => 0],
            ['date' => $yesterday, 'employee_no' => '1002', 'shift' => '早班', 'in' => '08:25:01', 'out' => '12:03:18', 'late' => 25, 'early' => 0],
            ['date' => $yesterday, 'employee_no' => '1003', 'shift' => '午班', 'in' => '12:01:20', 'out' => '18:00:00', 'late' => 1, 'early' => 0],
            ['date' => $yesterday, 'employee_no' => '1004', 'shift' => '晚班', 'in' => '17:55:30', 'out' => '22:00:12', 'late' => 0, 'early' => 0],
            ['date' => $yesterday, 'employee_no' => '1007', 'shift' => '早班', 'in' => '08:00:00', 'out' => '11:30:00', 'late' => 0, 'early' => 30],
            ['date' => $yesterday, 'employee_no' => '1008', 'shift' => '午班', 'in' => '12:00:00', 'out' => '18:00:00', 'late' => 0, 'early' => 0],
        ];

        foreach ($records as $r) {
            $user = $users[$r['employee_no']] ?? null;
            $shift = $shiftMap[$r['shift']] ?? null;
            if (! $user || ! $shift) {
                continue;
            }
            AttendanceRecord::updateOrCreate(
                ['user_id' => $user->id, 'date' => $r['date'], 'shift_id' => $shift->id],
                [
                    'plan_check_in_at' => $r['date']->copy()->setTimeFrom($shift->check_in_time),
                    'plan_check_out_at' => $r['date']->copy()->setTimeFrom($shift->check_out_time),
                    'check_in_at' => $r['date']->copy()->setTimeFromTimeString($r['in']),
                    'check_out_at' => $r['date']->copy()->setTimeFromTimeString($r['out']),
                    'late_minutes' => $r['late'],
                    'early_leave_minutes' => $r['early'],
                    'is_confirmed' => true,
                ]
            );
        }
    }

    private function seedLeaves(): void
    {
        $users = User::all()->keyBy('employee_no');
        $admin = $users['1001'] ?? null;

        $leaves = [
            ['employee_no' => '1002', 'type' => LeaveType::PERSONAL, 'reason' => '家中有事', 'start' => Carbon::yesterday(), 'end' => Carbon::yesterday(), 'substitute' => '1003', 'status' => ApproveStatus::APPROVED],
            ['employee_no' => '1004', 'type' => LeaveType::SICK, 'reason' => '身体不适', 'start' => Carbon::yesterday()->subDay(), 'end' => Carbon::yesterday(), 'substitute' => '1005', 'status' => ApproveStatus::APPROVED],
            ['employee_no' => '1006', 'type' => LeaveType::ANNUAL, 'reason' => '外出培训', 'start' => Carbon::yesterday()->subDays(3), 'end' => Carbon::yesterday()->subDay(), 'substitute' => '1007', 'status' => ApproveStatus::APPROVED],
            ['employee_no' => '1008', 'type' => LeaveType::PERSONAL, 'reason' => '私事', 'start' => Carbon::yesterday()->subDays(2), 'end' => Carbon::yesterday()->subDays(2), 'substitute' => '1001', 'status' => ApproveStatus::REJECTED],
        ];

        foreach ($leaves as $l) {
            $user = $users[$l['employee_no']] ?? null;
            $sub = $users[$l['substitute']] ?? null;
            if (! $user) {
                continue;
            }
            Leave::updateOrCreate(
                ['user_id' => $user->id, 'start_date' => $l['start'], 'end_date' => $l['end']],
                [
                    'applied_at' => $l['start']->copy()->subDay()->setTime(14, 20),
                    'type' => $l['type'],
                    'reason' => $l['reason'],
                    'substitute_user_id' => $sub?->id,
                    'approver_id' => $admin?->id,
                    'approve_status' => $l['status'],
                    'approved_at' => $l['status'] === ApproveStatus::APPROVED
                        ? $l['start']->copy()->subDay()->setTime(15, 0)
                        : $l['start']->copy()->subDay()->setTime(9, 15),
                ]
            );
        }
    }

    private function seedTodos(): void
    {
        $users = User::all()->keyBy('employee_no');
        $admin = $users['1001'] ?? null;

        Todo::query()->delete();
        TodoAssignee::query()->delete();

        $todos = [
            [
                'type' => TodoType::REPAIR, 'title' => '维修处理：A栋301室 水工-堵塞',
                'content' => '报修单跟修人处理。', 'status' => TodoStatus::PENDING,
                'assignees' => ['1003'],
            ],
            [
                'type' => TodoType::HANDOVER_OVERDUE, 'title' => '交接事项超 5 天未完成',
                'content' => '住宿退宿交接事项超期未完成。', 'status' => TodoStatus::PENDING,
                'assignees' => $admin ? ['1001'] : [],
            ],
        ];

        // 交接演示数据：分布在过去 9 天（完成/未完成混合），让工作管理「每日交接完成率」图表有连续柱形。
        // 注意：数量不符 / 报废提醒不走演示数据——假数字会与真实房间配置矛盾，一律由
        // MaterialAlertService 真机制在业务发生时生成。
        $handoverDemo = [
            [-9, 'completed'], [-8, 'completed'], [-7, 'completed'], [-6, 'completed'],
            [-5, 'completed'], [-4, 'pending'], [-3, 'completed'], [-2, 'completed'],
            [-1, 'pending'], [0, 'pending'],
        ];
        foreach ($handoverDemo as [$offset, $status]) {
            $todos[] = [
                'type' => TodoType::HANDOVER,
                'title' => '值班交接：'.now()->addDays($offset)->format('m-d').' 值班日志与钥匙',
                'content' => '值班日志、钥匙与电话接听记录交接，请确认。',
                'status' => $status === 'completed' ? TodoStatus::COMPLETED : TodoStatus::PENDING,
                'assignees' => ['1005', '1006'],
                'created_at' => now()->addDays($offset)->setTime(9, 30),
            ];
        }

        foreach ($todos as $t) {
            $todo = Todo::create([
                'type' => $t['type'],
                'title' => $t['title'],
                'content' => $t['content'],
                'status' => $t['status'],
                'source_type' => Todo::class,
                'source_id' => 0,
                'created_by' => $admin?->id,
            ]);
            // created_at 不在 $fillable 里，create() 传不进去，须单独 update 回溯日期
            if (! empty($t['created_at'])) {
                Todo::where('id', $todo->id)->update(['created_at' => $t['created_at'], 'updated_at' => $t['created_at']]);
            }

            foreach ($t['assignees'] as $no) {
                $u = $users[$no] ?? null;
                if ($u) {
                    TodoAssignee::create(['todo_id' => $todo->id, 'user_id' => $u->id]);
                }
            }
        }
    }

    /** 安全管理演示数据（需求 §7：检查列表 / 消防志愿服务 / 消防培训） */
    private function seedSafety(): void
    {
        $users = User::all()->keyBy('employee_no');
        $admin = $users['1001'] ?? null;
        $staff = [$users['1008']?->id, $users['1003']?->id, $users['1005']?->id]
            ? array_values(array_filter([$users['1008']?->id, $users['1003']?->id])) : [];

        // —— 7.1 检查列表（宿舍消防安全检查 = 逐房间用电安全表单，对齐原型）——
        SafetyCheck::query()->delete();
        $roomA301 = Room::where('room_no', 'A栋301室')->first()?->id;
        $roomB206 = Room::where('room_no', 'B栋206室')->first()?->id;
        $roomC410 = Room::where('room_no', 'C栋410室')->first()?->id;
        $roomD108 = Room::where('room_no', 'D栋108室')->first()?->id;
        $checks = [
            [
                'category' => SafetyCategory::DORM_FIRE, 'check_date' => '2026-08-26', 'room_id' => $roomA301,
                'private_wiring' => false, 'strip_overload' => false, 'high_wattage' => false,
                'power_off' => true, 'trash_at_door' => false, 'smoke_blocked' => false,
                'result' => CheckResult::NORMAL, 'rectify' => null,
                'external_inspector' => null, 'remark' => null,
            ],
            [
                'category' => SafetyCategory::DORM_FIRE, 'check_date' => '2026-07-15', 'room_id' => $roomB206,
                'private_wiring' => false, 'strip_overload' => false, 'high_wattage' => false,
                'power_off' => true, 'trash_at_door' => false, 'smoke_blocked' => false,
                'result' => CheckResult::NORMAL, 'rectify' => null,
                'external_inspector' => null, 'remark' => '已整改',
            ],
            [
                'category' => SafetyCategory::DORM_FIRE, 'check_date' => '2026-06-10', 'room_id' => $roomC410,
                'private_wiring' => true, 'strip_overload' => false, 'high_wattage' => false,
                'power_off' => true, 'trash_at_door' => false, 'smoke_blocked' => false,
                'result' => CheckResult::ABNORMAL, 'rectify' => 'pending',
                'external_inspector' => null, 'remark' => '发现私拉电线',
            ],
            [
                'category' => SafetyCategory::DORM_FIRE, 'check_date' => '2026-05-08', 'room_id' => $roomD108,
                'private_wiring' => false, 'strip_overload' => true, 'high_wattage' => false,
                'power_off' => true, 'trash_at_door' => false, 'smoke_blocked' => false,
                'result' => CheckResult::ABNORMAL, 'rectify' => 'pending',
                'external_inspector' => '物业外包人员某某', 'remark' => '发现大功率插座',
            ],
            [
                'category' => SafetyCategory::DORM_FIRE, 'check_date' => '2026-04-12', 'room_id' => $roomA301,
                'private_wiring' => false, 'strip_overload' => false, 'high_wattage' => false,
                'power_off' => true, 'trash_at_door' => false, 'smoke_blocked' => false,
                'result' => CheckResult::NORMAL, 'rectify' => null,
                'external_inspector' => null, 'remark' => '已整改',
            ],
            [
                'category' => SafetyCategory::PUBLIC_ORDER, 'check_date' => '2026-08-23',
                'event_desc' => '夜间楼道大声喧哗', 'event_time' => '2026-08-23 23:20',
                'place' => 'D栋 4 层楼道', 'person' => '返校学生 3 人',
                'process' => '值班员上前劝导，学生配合返回房间，次日班会上重申作息规定。',
            ],
            [
                'category' => SafetyCategory::PROPERTY, 'check_date' => '2026-08-23',
                'event_desc' => '学生报告晾晒鞋丢失', 'event_time' => '2026-08-23 18:00',
                'place' => '公共晾衣间', 'person' => 'D栋108室学生',
                'process' => '调取监控排查，次日鞋由他人误收后归还，双方现场确认无误。',
            ],
            [
                'category' => SafetyCategory::PERSONAL, 'check_date' => '2026-08-18',
                'event_desc' => '学生洗澡滑倒轻微擦伤', 'event_time' => '2026-08-18 21:40',
                'place' => 'A栋 2 层公共浴室', 'person' => 'A栋205室学生',
                'process' => '值班员陪同前往校医院处理，后续在浴室增设防滑垫并张贴提示。',
            ],
            [
                'category' => SafetyCategory::ENVIRONMENT, 'check_date' => '2026-08-16',
                'event_desc' => '公共厨房垃圾未及时清理', 'event_time' => '2026-08-16 09:30',
                'place' => 'C栋公共厨房', 'person' => '保洁员、值班员',
                'process' => '当天完成清理，增加一次晚间巡查频次。',
            ],
            [
                'category' => SafetyCategory::EMERGENCY, 'check_date' => '2026-08-22',
                'event_category' => EmergencyCategory::FIRE,
                'event_desc' => '公共厨房微波炉冒烟', 'event_time' => '2026-08-22 12:10',
                'place' => '公共厨房', 'person' => '在场学生 2 人',
                'process' => '立即断电开窗通风，确认无明火后张贴停用通知并报修更换。',
            ],
        ];

        foreach ($checks as $c) {
            $isCheck = in_array($c['category'], [SafetyCategory::DORM_FIRE, SafetyCategory::DORM_FIRE], true);
            SafetyCheck::create($c + [
                'inspector_ids' => $isCheck ? $staff : null,
                'created_by' => $admin?->id,
            ]);
        }

        // —— 7.2 消防志愿服务 ——
        FireVolunteer::query()->delete();
        $volunteers = [
            ['示例学员一', '2023001', '大二', '2026-08-20', '协助开展宿舍楼消防疏散演练引导', 5, '表现积极'],
            ['示例学员二', '2023002', '大三', '2026-08-18', '参与消防知识海报张贴与宣传', 3, null],
            ['示例学员三', '2023004', '研一', '2026-07-10', '消防知识进班级宣讲', 4, null],
            ['刘洋', '2023005', '大四', '2026-06-12', '消防演练现场引导与签到', 5, null],
            ['示例学员四', '2023006', '大二', '2026-05-15', '参与消防宣传海报制作', 3, null],
            ['示例学员五', '2023007', '大一', '2026-04-20', '灭火器及消防栓器材检查协助', 4, '协助完成台账'],
        ];
        foreach ($volunteers as $v) {
            FireVolunteer::create([
                'student_name' => $v[0], 'student_no' => $v[1], 'grade' => $v[2],
                'service_date' => $v[3], 'service_content' => $v[4], 'service_hours' => $v[5],
                'remark' => $v[6], 'created_by' => $admin?->id,
            ]);
        }

        // —— 7.3 消防培训 ——
        FireTraining::query()->delete();
        $trainings = [
            ['2026-08-22', '示例学员一', '2023001', '大二', '灭火器与消防栓实操培训', 2, '考核通过'],
            ['2026-07-30', '示例学员二', '2023002', '大三', '初期火灾扑救与报警流程培训', 3, null],
            ['2026-06-18', '示例学员六', '2023003', '大一', '新生消防知识第一课', 2, null],
            ['2026-04-10', '示例学员六', '2023003', '大一', '消防器材使用培训', 4, null],
            ['2026-02-20', '示例学员一', '2023001', '大二', '消防法律法规与责任培训', 3, null],
        ];
        foreach ($trainings as $t) {
            FireTraining::create([
                'train_date' => $t[0], 'trainer_name' => $t[1], 'student_no' => $t[2], 'grade' => $t[3],
                'train_content' => $t[4], 'train_hours' => $t[5], 'remark' => $t[6],
                'created_by' => $admin?->id,
            ]);
        }

        // —— §8 物资管理：物资列表 / 入库 / 出库（对齐原型 data.js 演示数据）——
        $this->seedMaterials($admin);
    }

    /** 物资模块演示数据（需求 §8：物资列表 + 入库管理 + 出库管理） */
    private function seedMaterials(?User $admin): void
    {
        Material::query()->delete();
        MaterialStock::query()->delete();

        // 物资列表（含 2 条已报废；投影仪 scrap_year=2026 用于验证报废提醒待办）
        $materials = [
            // kind, sub, name, purchase, scrap, scrapped, actual_scrap, location, use, furniture, code, qty, in_at, in_by, out_at, out_by, stock
            ['consumable', 'bedding', '棉被', 2024, 2028, false, null, 'A栋301室', 'life', null, 'BX-001', 60, '2024-09-01', '张明', null, null, 48],
            ['consumable', 'cleaning', '消毒液', 2026, 2027, false, null, 'A栋302室', 'life', null, 'QJ-001', 30, '2026-05-10', '周涛', null, null, 22],
            ['consumable', 'appliance', 'LED灯泡', 2025, 2027, false, null, 'B栋205室', 'office', null, 'JD-001', 200, '2025-03-15', '周涛', null, null, 165],
            ['fixed', null, '办公桌', 2023, 2033, false, null, 'B栋206室', 'office', '桌椅类', 'GZ-001', 10, '2023-06-01', '张明', null, null, 10],
            ['consumable', 'toiletries', '洗衣液', 2026, 2027, false, null, 'C栋410室', 'life', null, 'XH-001', 40, '2026-01-20', '张明', null, null, 12],
            ['fixed', null, '更衣柜', 2022, 2032, true, 2026, 'C栋411室', 'life', '柜类', 'GZ-002', 8, '2022-09-01', '张明', '2026-06-01', '周涛', 0],
            ['fixed', null, '投影仪', 2016, 2026, false, null, 'B栋206室', 'office', '其他', 'GZ-003', 2, '2016-03-01', '张明', null, null, 2],
            ['fixed', null, '单人床', 2023, 2033, false, null, 'A栋301室', 'life', '床类', 'GZ-004', 4, '2023-09-01', '张明', null, null, 4],
            ['fixed', null, '书桌椅', 2023, 2033, false, null, 'B栋205室', 'life', '桌椅类', 'GZ-005', 4, '2023-09-01', '张明', null, null, 4],
            ['fixed', null, '衣柜', 2023, 2033, false, null, 'A栋302室', 'life', '柜类', 'GZ-006', 4, '2023-09-01', '张明', null, null, 4],
            ['consumable', 'bedding', '旧床垫', 2021, 2026, true, 2026, 'D栋108室', 'life', null, 'BX-002', 20, '2021-03-01', '周涛', '2026-03-15', '周涛', 0],
        ];
        foreach ($materials as $m) {
            Material::create([
                'kind' => $m[0], 'consumable_sub' => $m[1], 'name' => $m[2],
                'purchase_year' => $m[3], 'scrap_year' => $m[4],
                'scrapped' => $m[5], 'actual_scrap_year' => $m[6],
                'location' => $m[7], 'use_type' => $m[8], 'furniture_kind' => $m[9],
                'code' => $m[10], 'qty' => $m[11],
                'stock_in_at' => $m[12], 'stock_in_by' => $m[13],
                'stock_out_at' => $m[14], 'stock_out_by' => $m[15],
                'stock' => $m[16], 'created_by' => $admin?->id,
            ]);
        }

        // 入库流水
        $inbounds = [
            ['fixed', '办公桌', 10, 'GZ-001', '2026-08-20', '张明', 'B栋206室', '新学期补充'],
            ['consumable', '棉被', 60, null, '2026-09-01', '周涛', '地下库', '迎新物资'],
            ['consumable', '消毒液', 30, null, '2026-09-05', '周涛', '1133库房', null],
            ['fixed', '更衣柜', 8, 'GZ-002', '2026-09-10', '张明', 'C栋411室', null],
        ];
        foreach ($inbounds as $s) {
            MaterialStock::create([
                'direction' => 'in', 'type' => $s[0], 'category' => $s[1], 'qty' => $s[2],
                'code' => $s[3], 'stock_date' => $s[4], 'manager' => $s[5],
                'location' => $s[6], 'remark' => $s[7], 'created_by' => $admin?->id,
            ]);
        }

        // 出库流水
        $outbounds = [
            ['consumable', '棉被', 12, null, '2026-09-02', '周涛', 'A栋301室', '新生领取'],
            ['fixed', '办公桌', 2, 'GZ-001', '2026-09-03', '张明', 'B栋206室', null],
            ['consumable', '消毒液', 8, null, '2026-09-08', '周涛', 'A栋302室', null],
        ];
        foreach ($outbounds as $s) {
            MaterialStock::create([
                'direction' => 'out', 'type' => $s[0], 'category' => $s[1], 'qty' => $s[2],
                'code' => $s[3], 'stock_date' => $s[4], 'manager' => $s[5],
                'location' => $s[6], 'remark' => $s[7], 'created_by' => $admin?->id,
            ]);
        }
    }
}
