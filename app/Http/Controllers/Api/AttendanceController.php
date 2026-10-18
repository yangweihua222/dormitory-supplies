<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\Schedule;
use App\Models\User;
use App\Support\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class AttendanceController extends Controller
{
    use ApiResponse;

    /**
     * 考勤记录列表
     * GET /api/attendance
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        // Carbon 绑定（而非裸字符串）：SQLite 的 date 列存完整时间串，裸字符串比较会漏行
        $startDate = Carbon::parse($request->input('start_date', Carbon::today()->subDays(6)->toDateString()));
        $endDate = Carbon::parse($request->input('end_date', Carbon::today()->toDateString()));

        $query = Schedule::with(['user', 'shift'])
            ->leftJoin('attendance_records', function ($join) {
                $join->on('attendance_records.user_id', '=', 'schedules.user_id')
                    ->on('attendance_records.date', '=', 'schedules.date')
                    ->on('attendance_records.shift_id', '=', 'schedules.shift_id');
            })
            ->whereBetween('schedules.date', [$startDate, $endDate])
            ->select([
                'schedules.*',
                'attendance_records.id as record_id',
                'attendance_records.check_in_at',
                'attendance_records.check_out_at',
                'attendance_records.late_minutes',
                'attendance_records.early_leave_minutes',
                'attendance_records.returned_cards',
                'attendance_records.returned_keys',
                'attendance_records.is_confirmed',
                'attendance_records.remark',
                'attendance_records.created_at as record_created_at',
                'attendance_records.updated_at as record_updated_at',
            ]);

        // 数据范围
        if (! $user->isAdmin()) {
            $query->where('schedules.user_id', $user->id);
        } elseif ($request->filled('user_id')) {
            $query->where('schedules.user_id', $request->input('user_id'));
        }

        // 姓名关键字
        if ($kw = trim((string) $request->input('keyword', ''))) {
            $query->whereHas('user', fn ($q) => $q->where('username', 'like', "%{$kw}%"));
        }

        $paginator = $query->orderBy('schedules.date', 'desc')
            ->orderBy('schedules.user_id')
            ->paginate((int) $request->input('pageSize', 10));

        $items = $paginator->getCollection()->map(fn ($s) => $this->scheduleToRow($s));
        $paginator->setCollection($items);

        return $this->page($paginator);
    }

    /**
     * 考勤统计卡片
     * GET /api/attendance/stats
     */
    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();
        $startDate = Carbon::parse($request->input('start_date', Carbon::today()->subDays(6)->toDateString()));
        $endDate = Carbon::parse($request->input('end_date', Carbon::today()->toDateString()));

        $scheduleQuery = Schedule::whereBetween('schedules.date', [$startDate, $endDate]);
        if (! $user->isAdmin()) {
            $scheduleQuery->where('schedules.user_id', $user->id);
        } elseif ($request->filled('user_id')) {
            $scheduleQuery->where('schedules.user_id', $request->input('user_id'));
        }

        $schedules = $scheduleQuery->leftJoin('attendance_records', function ($join) {
            $join->on('attendance_records.user_id', '=', 'schedules.user_id')
                ->on('attendance_records.date', '=', 'schedules.date')
                ->on('attendance_records.shift_id', '=', 'schedules.shift_id');
        })
            ->select([
                'schedules.*',
                'attendance_records.id as record_id',
                'attendance_records.check_in_at',
                'attendance_records.late_minutes',
                'attendance_records.early_leave_minutes',
            ])
            ->get();

        $expected = $schedules->count();
        $actual = 0;
        $late = 0;
        $early = 0;
        $absent = 0;
        $leave = 0;
        $pending = 0;

        foreach ($schedules as $schedule) {
            $status = $this->attendanceStatus($schedule);
            if ($status === 'leave') {
                $leave++;
            } elseif ($status === 'absent') {
                $absent++;
            } elseif ($status === 'pending') {
                $pending++;
            } else {
                $actual++;
                if ($status === 'late') {
                    $late++;
                } elseif ($status === 'early') {
                    $early++;
                } elseif ($status === 'late_early') {
                    $late++;
                    $early++;
                }
            }
        }

        return $this->ok([
            'expected' => $expected,
            'actual' => $actual,
            'late' => $late,
            'early' => $early,
            'absent' => $absent,
            'leave' => $leave,
            'pending' => $pending,
            'date_range' => ['start' => $startDate->toDateString(), 'end' => $endDate->toDateString()],
        ]);
    }

    /**
     * 导出考勤记录（按模板）
     * GET /api/attendance/export
     */
    public function export(Request $request)
    {
        $user = $request->user();
        $startDate = Carbon::parse($request->input('start_date', Carbon::today()->subDays(6)->toDateString()));
        $endDate = Carbon::parse($request->input('end_date', Carbon::today()->toDateString()));
        $startCarbon = $startDate->copy();
        $endCarbon = $endDate->copy();

        // 加载模板
        $templatePath = storage_path('app/templates/attendance.xlsx');
        if (! file_exists($templatePath)) {
            return $this->fail(5001, '考勤导出模板不存在，请联系管理员');
        }

        // 查询排班与考勤（按模板只需要状态）
        $query = Schedule::with(['user', 'shift'])
            ->leftJoin('attendance_records', function ($join) {
                $join->on('attendance_records.user_id', '=', 'schedules.user_id')
                    ->on('attendance_records.date', '=', 'schedules.date')
                    ->on('attendance_records.shift_id', '=', 'schedules.shift_id');
            })
            ->whereBetween('schedules.date', [$startDate, $endDate])
            ->select(['schedules.*', 'attendance_records.id as record_id', 'attendance_records.check_in_at']);

        if (! $user->isAdmin()) {
            $query->where('schedules.user_id', $user->id);
        } elseif ($request->filled('user_id')) {
            $query->where('schedules.user_id', $request->input('user_id'));
        }

        if ($kw = trim((string) $request->input('keyword', ''))) {
            $query->whereHas('user', fn ($q) => $q->where('username', 'like', "%{$kw}%"));
        }

        $schedules = $query->get();

        // 以日期+用户聚合状态（一人一天只取一条，有多个班次取最严重的）
        $matrix = [];
        foreach ($schedules as $schedule) {
            $dateKey = $schedule->date->format('Y-m-d');
            $userId = $schedule->user_id;
            $status = $this->attendanceStatus($schedule);
            $matrix[$dateKey][$userId] = $this->worseStatus($matrix[$dateKey][$userId] ?? null, $status);
        }

        // 涉及的用户（按 id 排序）
        $userIds = [];
        foreach ($matrix as $dateMatrix) {
            $userIds = array_merge($userIds, array_keys($dateMatrix));
        }
        $userIds = array_unique($userIds);
        sort($userIds, SORT_NUMERIC);
        $users = User::whereIn('id', $userIds)->orderBy('id')->pluck('username', 'id');

        $spreadsheet = IOFactory::load($templatePath);
        $sheet = $spreadsheet->getActiveSheet();
        $highestRow = $sheet->getHighestRow();
        $highestCol = $sheet->getHighestColumn();

        // 清空原模板数据区域（保留样式）
        for ($r = 1; $r <= $highestRow; $r++) {
            for ($c = 1; $c <= Coordinate::columnIndexFromString($highestCol); $c++) {
                $sheet->setCellValue($this->colLetter($c).$r, '');
            }
        }

        // 标题：2026年9月考勤表
        $sheet->setCellValue('A1', $startCarbon->format('Y年n月').'考勤表');

        // 表头：日期\姓名 | 用户1 | 用户2 | ...
        $sheet->setCellValue('A2', '日期\姓名');
        $col = 2; // B 列开始
        foreach ($users as $userId => $username) {
            $sheet->setCellValue($this->colLetter($col).'2', $username);
            $col++;
        }

        // 数据行
        $row = 3;
        $current = $startCarbon->copy();
        while ($current->lte($endCarbon)) {
            $dateKey = $current->format('Y-m-d');
            $sheet->setCellValue('A'.$row, Date::PHPToExcel($current->timestamp));
            $sheet->getStyle('A'.$row)
                ->getNumberFormat()
                ->setFormatCode('yyyy-mm-dd');

            $col = 2;
            foreach ($users as $userId => $username) {
                $status = $matrix[$dateKey][$userId] ?? 'pending';
                $sheet->setCellValue($this->colLetter($col).$row, $this->statusMark($status));
                $col++;
            }

            $current->addDay();
            $row++;
        }

        // 底部签名行位置动态调整
        $footerStartRow = $row + 1;
        $sheet->setCellValue('A'.$footerStartRow, '工作人员：');
        $sheet->setCellValue('A'.($footerStartRow + 1), '考勤员：');
        $sheet->setCellValue('A'.($footerStartRow + 2), '部门领导：');
        $sheet->setCellValue('A'.($footerStartRow + 3), '主管院领导：');

        $fileName = "考勤表_{$startCarbon->format('Y年n月')}.xlsx";

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');

        return response()->streamDownload(function () use ($writer, $spreadsheet) {
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * 签到
     * POST /api/attendance/sign-in
     */
    public function signIn(Request $request): JsonResponse
    {
        $user = $request->user();
        $today = Carbon::today();

        // 当天无排班时回看昨日夜班（跨天班次尚未结束）→ 支持过零点补签
        $schedule = Schedule::findSignableSchedule($user->id, $today);

        if (! $schedule) {
            return $this->fail(4002, '今日无排班，无需签到');
        }

        // 请假中的排班不允许签到（请假审批通过后排班已置为请假状态）
        if ($schedule->status->value === 'leave' || $schedule->leave_id) {
            return $this->fail(4007, '今日请假中，无需签到');
        }

        // 考勤记录归属「排班当天」（夜班跨零点补签时为昨天）
        $record = AttendanceRecord::firstOrNew([
            'user_id' => $user->id,
            'date' => $schedule->date,
            'shift_id' => $schedule->shift_id,
        ], [
            'plan_check_in_at' => $schedule->date->copy()->setTimeFrom($schedule->shift->check_in_time),
            'plan_check_out_at' => $this->planCheckOutAt($schedule->date, $schedule->shift),
        ]);

        if ($record->check_in_at) {
            return $this->fail(4001, '今日已签到，无需重复签到');
        }

        $now = Carbon::now();
        $record->check_in_at = $now;

        // 迟到计算
        $planCheckIn = $record->plan_check_in_at;
        $lateMinutes = 0;
        if ($now->greaterThan($planCheckIn)) {
            $lateMinutes = (int) ceil($now->diffInMinutes($planCheckIn, false) * -1);
            $lateMinutes = max(0, $lateMinutes);
        }
        $record->late_minutes = $lateMinutes;
        $record->save();

        return $this->ok([
            'check_in_at' => $now->format('H:i:s'),
            'late_minutes' => $lateMinutes,
        ], '签到成功');
    }

    /**
     * 签退
     * POST /api/attendance/sign-out
     */
    public function signOut(Request $request): JsonResponse
    {
        $request->validate([
            'returned_cards' => 'nullable|integer|min:0',
            'returned_keys' => 'nullable|integer|min:0',
            'is_confirmed' => 'required|boolean',
        ]);

        $user = $request->user();
        $today = Carbon::today();

        if (! $request->boolean('is_confirmed')) {
            return $this->fail(4003, '请先确认物品交接核对');
        }

        // 签退目标：最近一条「已签到未签退」的开放记录（而非只看今天）——
        // 跨天值班（22:00-次日08:00）的签退发生在次日早上，记录挂在排班当天
        $record = AttendanceRecord::with('shift')
            ->where('user_id', $user->id)
            ->whereNotNull('check_in_at')
            ->whereNull('check_out_at')
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->first();

        if (! $record) {
            // 没有开放记录：区分「今天已签退」「今天没签到」「今天没记录」
            $todayRecord = AttendanceRecord::where('user_id', $user->id)
                ->where('date', $today)
                ->first();

            if ($todayRecord && $todayRecord->check_in_at && $todayRecord->check_out_at) {
                return $this->fail(4006, '今日已签退，无需重复签退');
            }
            if ($todayRecord && ! $todayRecord->check_in_at) {
                return $this->fail(4005, '尚未签到，无法签退');
            }

            return $this->fail(4004, '今日无签到记录，无法签退');
        }

        $now = Carbon::now();
        $record->check_out_at = $now;
        $record->returned_cards = $request->input('returned_cards', 0);
        $record->returned_keys = $request->input('returned_keys', 0);
        $record->is_confirmed = true;

        // 早退计算
        $planCheckOut = $record->plan_check_out_at;
        $earlyMinutes = 0;
        if ($now->lessThan($planCheckOut)) {
            $earlyMinutes = (int) ceil($planCheckOut->diffInMinutes($now, false) * -1);
            $earlyMinutes = max(0, $earlyMinutes);
        }
        $record->early_leave_minutes = $earlyMinutes;
        $record->save();

        return $this->ok([
            'check_out_at' => $now->format('H:i:s'),
            'early_leave_minutes' => $earlyMinutes,
        ], '签退成功');
    }

    /**
     * 今日考勤状态（用于实时刷新）
     * GET /api/attendance/today
     */
    public function today(Request $request): JsonResponse
    {
        $user = $request->user();
        $today = Carbon::today();

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

        // 回看昨日夜班时，计划时间补「昨日 / 次日」标注
        $crossDayFallback = $schedule && $schedule->date->toDateString() !== $today->toDateString();
        $planIn = $schedule?->shift?->check_in_time?->format('H:i:s');
        $planOut = $schedule?->shift?->check_out_time?->format('H:i:s');
        if ($crossDayFallback) {
            $planIn = $planIn === null ? null : '昨日'.$planIn;
            $planOut = $planOut === null ? null : '次日'.$planOut;
        }

        return $this->ok([
            'shift_name' => $schedule?->shift?->name ?? null,
            'plan_check_in' => $planIn,
            'plan_check_out' => $planOut,
            'check_in_at' => $record?->check_in_at?->format('H:i:s') ?? null,
            'check_out_at' => $record?->check_out_at?->format('H:i:s') ?? null,
            'returned_cards' => $record?->returned_cards ?? 0,
            'returned_keys' => $record?->returned_keys ?? 0,
            'is_confirmed' => (bool) ($record?->is_confirmed ?? false),
            'leave' => (bool) $leave,
            'status' => $status,
        ]);
    }

    private function scheduleToRow(Schedule $schedule): array
    {
        $status = $this->attendanceStatus($schedule);

        // 跨天班次：签退时间 <= 签到时间 视为次日
        $shift = $schedule->shift;
        $crossDay = $shift && $shift->check_out_time && $shift->check_in_time
            && $shift->check_out_time->format('H:i:s') <= $shift->check_in_time->format('H:i:s');

        return [
            'id' => $schedule->record_id,
            'schedule_id' => $schedule->id,
            'user_id' => $schedule->user_id,
            'username' => $schedule->user?->username,
            'date' => $schedule->date->format('Y-m-d'),
            'shift_name' => $schedule->shift?->name,
            'shift_type' => $schedule->shift?->type?->value,
            'shift_type_label' => $schedule->shift?->type?->label(),
            'plan_check_in' => $this->formatTime($schedule->shift?->check_in_time),
            'plan_check_out' => $this->appendNextDay($this->formatTime($schedule->shift?->check_out_time), $crossDay),
            'check_in_at' => $this->formatRecordTime($schedule->check_in_at, $schedule->date),
            'check_out_at' => $this->formatRecordTime($schedule->check_out_at, $schedule->date),
            'late_minutes' => (int) ($schedule->late_minutes ?? 0),
            'early_leave_minutes' => (int) ($schedule->early_leave_minutes ?? 0),
            'returned_cards' => (int) ($schedule->returned_cards ?? 0),
            'returned_keys' => (int) ($schedule->returned_keys ?? 0),
            'status' => $status,
            'status_label' => $this->statusLabel($status),
            'is_confirmed' => (bool) ($schedule->is_confirmed ?? false),
            'remark' => $schedule->remark,
            'created_at' => $this->formatDateTime($schedule->record_created_at),
            'updated_at' => $this->formatDateTime($schedule->record_updated_at),
        ];
    }

    private function attendanceStatus(Schedule $schedule): string
    {
        if ($schedule->status->value === 'leave' || $schedule->leave_id) {
            return 'leave';
        }

        $hasRecord = ! is_null($schedule->record_id);
        if (! $hasRecord || is_null($schedule->check_in_at)) {
            // 计划签到时间已过才算缺卡，否则只是未签到
            $planCheckIn = $schedule->date->copy()->setTimeFrom($schedule->shift?->check_in_time ?? '00:00:00');
            if (Carbon::now()->greaterThan($planCheckIn)) {
                return 'absent';
            }

            return 'pending';
        }

        // 已签到但尚未签退：迟到已定（照常标记），否则显示「未签退」而非「正常」
        if (is_null($schedule->check_out_at)) {
            if (($schedule->late_minutes ?? 0) > 0) {
                return 'late';
            }

            return 'not_out';
        }

        $isLate = ($schedule->late_minutes ?? 0) > 0;
        $isEarly = ($schedule->early_leave_minutes ?? 0) > 0;

        if ($isLate && $isEarly) {
            return 'late_early';
        }
        if ($isLate) {
            return 'late';
        }
        if ($isEarly) {
            return 'early';
        }

        return 'normal';
    }

    private function formatTime($value): ?string
    {
        if (is_null($value)) {
            return null;
        }
        if ($value instanceof Carbon) {
            return $value->format('H:i:s');
        }
        try {
            return Carbon::parse($value)->format('H:i:s');
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * 跨天班次：时间后追加（次日）标注
     */
    private function appendNextDay(?string $time, bool $crossDay): ?string
    {
        if (is_null($time)) {
            return null;
        }

        return $crossDay ? $time.'（次日）' : $time;
    }

    /**
     * 实际打卡时间：若与排班日期不是同一天，追加（次日）标注
     */
    private function formatRecordTime($value, Carbon $scheduleDate): ?string
    {
        $time = $this->formatTime($value);
        if (is_null($time)) {
            return null;
        }

        try {
            $date = $value instanceof Carbon ? $value : Carbon::parse($value);

            return $date->format('Y-m-d') === $scheduleDate->format('Y-m-d')
                ? $time
                : $time.'（次日）';
        } catch (\Throwable $e) {
            return $time;
        }
    }

    private function formatDateTime($value): ?string
    {
        if (is_null($value)) {
            return null;
        }
        if ($value instanceof Carbon) {
            return $value->format('Y-m-d H:i:s');
        }
        try {
            return Carbon::parse($value)->format('Y-m-d H:i:s');
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'normal' => '正常',
            'late' => '迟到',
            'early' => '早退',
            'late_early' => '迟到+早退',
            'absent' => '缺卡',
            'leave' => '请假',
            'pending' => '未签到',
            'not_out' => '未签退',
            default => $status,
        };
    }

    private function statusMark(string $status): string
    {
        return match ($status) {
            'normal' => '√',
            'late' => '迟',
            'early' => '退',
            'late_early' => '迟/退',
            'absent' => '缺',
            'leave' => '假',
            'pending' => '',
            default => '',
        };
    }

    private function colLetter(int $column): string
    {
        $letter = '';
        while ($column > 0) {
            $mod = ($column - 1) % 26;
            $letter = chr(65 + $mod).$letter;
            $column = (int) (($column - $mod) / 26);
        }

        return $letter;
    }

    private function worseStatus(?string $existing, string $new): string
    {
        $severity = [
            'pending' => 0,
            'normal' => 1,
            'not_out' => 2,
            'leave' => 2,
            'late' => 3,
            'early' => 3,
            'late_early' => 4,
            'absent' => 5,
        ];

        if ($existing === null) {
            return $new;
        }

        return ($severity[$existing] ?? 0) >= ($severity[$new] ?? 0) ? $existing : $new;
    }

    private function planCheckOutAt(Carbon $date, $shift): Carbon
    {
        $out = $date->copy()->setTimeFrom($shift->check_out_time);
        // 跨天班次：签退时间 <= 签到时间 则视为次日
        if ($shift->check_out_time->format('H:i:s') <= $shift->check_in_time->format('H:i:s')) {
            $out = $out->addDay();
        }

        return $out;
    }
}
