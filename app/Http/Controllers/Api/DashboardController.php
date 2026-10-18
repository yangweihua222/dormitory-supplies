<?php

namespace App\Http\Controllers\Api;

use App\Enums\TodoStatus;
use App\Enums\TodoType;
use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\Schedule;
use App\Models\Shift;
use App\Models\Todo;
use App\Models\User;
use App\Support\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $today = Carbon::today();

        // 1. 快速入口：按权限过滤的全部菜单项
        $quickEntries = $this->quickEntries($user);

        // 2. 今日排班与签到状态
        $signInfo = $this->todaySignInfo($user, $today);

        // 3. 今日值班人员（值班的 schedule）
        $dutyUsers = $this->todayDutyUsers($today);

        // 4. 待办：数据范围按需求 §1.1
        $todos = $this->todos($user);

        // 5. 管理员额外统计
        $stats = $user->isAdmin() ? $this->adminStats($today) : null;

        return $this->ok([
            'date' => $today->format('Y-m-d'),
            'weekday' => $this->weekday($today),
            'quick_entries' => $quickEntries,
            'sign_info' => $signInfo,
            'duty_users' => $dutyUsers,
            'todos' => $todos,
            'stats' => $stats,
        ]);
    }

    private function quickEntries(User $user): array
    {
        $entries = [
            ['key' => 'attendance', 'label' => '考勤统计', 'icon' => 'clock'],
            ['key' => 'shift', 'label' => '班次管理', 'icon' => 'shift'],
            ['key' => 'schedule', 'label' => '排班管理', 'icon' => 'schedule'],
            ['key' => 'dutylog', 'label' => '值班日志', 'icon' => 'duty'],
            ['key' => 'calendar', 'label' => '排班日历', 'icon' => 'calendar'],
            ['key' => 'leave', 'label' => '请假列表', 'icon' => 'leave'],
            ['key' => 'leavebalance', 'label' => '假期管理', 'icon' => 'calendar'],
            ['key' => 'safety', 'label' => '检查列表', 'icon' => 'shield'],
            ['key' => 'volunteer', 'label' => '消防志愿服务', 'icon' => 'users'],
            ['key' => 'training', 'label' => '消防培训', 'icon' => 'list'],
            ['key' => 'accommodation', 'label' => '住宿管理', 'icon' => 'home'],
            ['key' => 'card', 'label' => '房卡管理', 'icon' => 'card'],
            ['key' => 'repair', 'label' => '报修管理', 'icon' => 'tool'],
            ['key' => 'material', 'label' => '物资列表', 'icon' => 'box'],
            ['key' => 'inbound', 'label' => '入库管理', 'icon' => 'download'],
            ['key' => 'outbound', 'label' => '出库管理', 'icon' => 'upload'],
            ['key' => 'assess', 'label' => '月度考核', 'icon' => 'list'],
            ['key' => 'assess-appeal', 'label' => '考核申诉', 'icon' => 'edit'],
            ['key' => 'peer-review', 'label' => '同行评议', 'icon' => 'users'],
            ['key' => 'user', 'label' => '用户管理', 'icon' => 'user'],
        ];

        $keys = $user->menuKeys();

        return array_values(array_filter($entries, fn ($e) => in_array($e['key'], $keys, true)));
    }

    private function todaySignInfo(User $user, Carbon $today): array
    {
        // 当天无排班时回看昨日夜班：跨零点期间仍显示该班次状态（可补签）
        $schedule = Schedule::findSignableSchedule($user->id, $today);

        $record = null;
        if ($schedule) {
            $record = AttendanceRecord::with('shift')
                ->where('user_id', $user->id)
                ->where('date', $schedule->date)
                ->where('shift_id', $schedule->shift_id)
                ->first();
        }

        $leave = $schedule && ($schedule->status->value === 'leave' || $schedule->leave_id);

        $status = '今日无排班';
        if ($leave) {
            $status = '请假';
        } elseif ($record && $record->check_in_at) {
            $status = $record->check_out_at ? '已签退' : '已签到';
        } elseif ($schedule) {
            $status = '未签到';
        }

        $planIn = $schedule?->shift?->check_in_time?->format('H:i:s') ?? '—';
        $planOut = $schedule?->shift?->check_out_time?->format('H:i:s') ?? '—';

        // 跨天班次：值班签退计划显示 "次日08:00"
        if ($schedule && $schedule->shift && $schedule->shift->type->value === 'duty') {
            $planOut = '次日'.$planOut;
        }

        // 回看昨日夜班（过零点补签场景）：计划签到标注 "昨日"
        if ($schedule && $schedule->date->toDateString() !== $today->toDateString()) {
            $planIn = '昨日'.$planIn;
        }

        return [
            'shift_name' => $schedule?->shift?->name ?? '—',
            'shift_type' => $schedule?->shift?->type?->label() ?? '—',
            'plan_check_in' => $planIn,
            'plan_check_out' => $planOut,
            'check_in_at' => $record?->check_in_at?->format('H:i:s') ?? '—',
            'check_out_at' => $record?->check_out_at?->format('H:i:s') ?? '—',
            'status' => $status,
            'record_id' => $record?->id,
            'schedule_id' => $schedule?->id,
            'shift_id' => $schedule?->shift_id,
            'returned_cards' => $record?->returned_cards ?? 0,
            'returned_keys' => $record?->returned_keys ?? 0,
        ];
    }

    private function todayDutyUsers(Carbon $today): array
    {
        $dutyShift = Shift::where('type', 'duty')->first();
        if (! $dutyShift) {
            return [];
        }

        return Schedule::with('user')
            ->where('date', $today)
            ->where('shift_id', $dutyShift->id)
            ->get()
            ->map(fn ($s) => [
                'id' => $s->user_id,
                'name' => $s->user->username,
            ])
            ->toArray();
    }

    /** 待办：与 /api/todos 共用 Todo::feed()，避免首屏与分页两套口径 */
    private function todos(User $user): array
    {
        return Todo::feed($user, 1);
    }

    private function adminStats(Carbon $today): array
    {
        $monthStart = $today->copy()->startOfMonth();
        $monthEnd = $today->copy()->endOfMonth();

        $handoverTotal = Todo::whereIn('type', [TodoType::HANDOVER, TodoType::HANDOVER_OVERDUE])
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->count();

        $handoverDone = Todo::where('type', TodoType::HANDOVER)
            ->where('status', TodoStatus::COMPLETED)
            ->whereBetween('completed_at', [$monthStart, $monthEnd])
            ->count();

        // 今日已签人数：今日有签到记录且未签退，或已签退都算
        $signedCount = AttendanceRecord::where('date', $today)
            ->whereNotNull('check_in_at')
            ->distinct('user_id')
            ->count('user_id');

        return [
            'handover_total' => $handoverTotal,
            'handover_done' => $handoverDone,
            'handover_rate' => $handoverTotal > 0 ? round($handoverDone / $handoverTotal * 100) : 0,
            'signed_count' => $signedCount,
        ];
    }

    private function weekday(Carbon $date): string
    {
        return ['星期日', '星期一', '星期二', '星期三', '星期四', '星期五', '星期六'][$date->dayOfWeek];
    }
}
