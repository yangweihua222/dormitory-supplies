<?php

namespace App\Http\Controllers\Api;

use App\Enums\MaterialKind;
use App\Enums\RoomType;
use App\Enums\SafetyCategory;
use App\Enums\ScheduleStatus;
use App\Enums\TodoStatus;
use App\Enums\TodoType;
use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\AttendanceRecord;
use App\Models\FireTraining;
use App\Models\FireVolunteer;
use App\Models\Material;
use App\Models\MaterialStock;
use App\Models\Room;
use App\Models\SafetyCheck;
use App\Models\Schedule;
use App\Models\Todo;
use App\Support\ApiResponse;
use App\Support\ExportsExcel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 工作管理（需求 §9 年度统计 Dashboard，只读聚合，不产生写入）
 * 三大块卡片（志愿服务 / 消防培训 / 安全管理）+ §9.1 六项运营统计：
 * 1 当年采购（按类型） 2 当年报废（按类型） 3 宿舍在用物资（按家具种类）
 * 4 当月到岗率 5 当月准点率 6 每日交接完成率（最近 7 个有交接的日期）
 * + 月度考核统计（按年=全年各月平均分 / 按月=当月得分，与原型 renderAssessStatCard 一致）
 */
class WorkController extends Controller
{
    use ApiResponse;
    use ExportsExcel;

    public function stats(Request $request): JsonResponse
    {
        $year = (int) $request->input('year', now()->format('Y'));

        // —— 志愿服务（所选年度）——
        $vol = FireVolunteer::query()->whereYear('service_date', $year);
        $volunteer = [
            'students' => (clone $vol)->whereNotNull('student_no')->distinct('student_no')->count('student_no'),
            'count' => (clone $vol)->count(),
            'hours' => (float) (clone $vol)->sum('service_hours'),
        ];
        $svcByMonth = array_fill(0, 12, 0);
        foreach ((clone $vol)->selectRaw('MONTH(service_date) m, COUNT(*) c')->groupBy('m')->pluck('c', 'm') as $m => $c) {
            $svcByMonth[(int) $m - 1] = (int) $c;
        }

        // —— 消防培训（所选年度）——
        $tr = FireTraining::query()->whereYear('train_date', $year);
        $training = [
            'count' => (clone $tr)->count(),
            'students' => (clone $tr)->whereNotNull('student_no')->distinct('student_no')->count('student_no'),
            'hours' => (float) (clone $tr)->sum('train_hours'),
        ];
        $trByMonth = array_fill(0, 12, 0);
        foreach ((clone $tr)->selectRaw('MONTH(train_date) m, COUNT(*) c')->groupBy('m')->pluck('c', 'm') as $m => $c) {
            $trByMonth[(int) $m - 1] = (int) $c;
        }

        // —— 安全管理（所选年度；异常口径对齐原型：事件类即异常，检查类看结果）——
        $checks = SafetyCheck::query()->whereYear('check_date', $year)->get(['category', 'result', 'rectify']);
        $abnormal = $checks->filter(fn ($c) => $c->category !== SafetyCategory::DORM_FIRE || $c->result?->value === 'abnormal')->count();
        $safety = [
            'check_count' => $checks->count(),
            'room_count' => Room::count(),
            'abnormal' => $abnormal,
            'rect_done' => $checks->where('rectify', 'done')->count(),
            'rect_undone' => $checks->where('rectify', 'pending')->count(),
        ];

        // —— §9.1-1 当年采购（入库流水按类型汇总数量）——
        $buy = MaterialStock::query()->where('direction', 'in')->whereYear('stock_date', $year)
            ->selectRaw('type, SUM(qty) total')->groupBy('type')->pluck('total', 'type');
        $buyFixed = (int) ($buy[MaterialKind::FIXED->value] ?? 0);
        $buyCons = (int) ($buy[MaterialKind::CONSUMABLE->value] ?? 0);

        // —— §9.1-2 当年报废（已报废且实际报废年限=统计年度；未填实际年限的按出库时间兜底）——
        $scrapRows = Material::query()->where('scrapped', true)
            ->where(function ($q) use ($year) {
                $q->where('actual_scrap_year', $year)
                    ->orWhere(function ($q2) use ($year) {
                        $q2->whereNull('actual_scrap_year')->whereYear('stock_out_at', $year);
                    });
            })->get(['kind', 'qty']);
        $scrapFixed = (int) $scrapRows->where('kind', MaterialKind::FIXED)->sum('qty');
        $scrapCons = (int) $scrapRows->where('kind', MaterialKind::CONSUMABLE)->sum('qty');

        // —— §9.1-3 宿舍在用物资（房型=宿舍的房间内物资，按家具种类汇总）——
        $dormNos = Room::query()->where('type', RoomType::DORM)->pluck('room_no');
        $inUse = Material::query()->whereIn('location', $dormNos)
            ->whereIn('furniture_kind', ['桌椅类', '床类', '柜类'])
            ->selectRaw('furniture_kind, SUM(qty) total')->groupBy('furniture_kind')
            ->pluck('total', 'furniture_kind');
        $inUseByKind = [
            '桌椅类' => (int) ($inUse['桌椅类'] ?? 0),
            '床类' => (int) ($inUse['床类'] ?? 0),
            '柜类' => (int) ($inUse['柜类'] ?? 0),
        ];

        // —— §9.1-4/5 当月到岗率 / 准点率（真工程取当前自然月；分母含请假人次）——
        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd = now()->endOfMonth()->toDateString();
        $attMonth = AttendanceRecord::query()->whereBetween('date', [$monthStart, $monthEnd])->get();
        $leaveCnt = Schedule::query()->where('status', ScheduleStatus::LEAVE)
            ->whereBetween('date', [$monthStart, $monthEnd])->count();
        $onDuty = $attMonth->filter(fn ($r) => $r->check_in_at && $r->check_out_at);
        $onTime = $onDuty->filter(fn ($r) => ! $r->late_minutes && ! $r->early_leave_minutes);
        $attTotal = $attMonth->count() + $leaveCnt;
        $duty = [
            'month' => now()->format('Y-m'),
            'total' => $attTotal,
            'on_duty' => $onDuty->count(),
            'on_time' => $onTime->count(),
            'leave' => $leaveCnt,
            'duty_rate' => $attTotal ? round($onDuty->count() / $attTotal * 100) : 0,
            'time_rate' => $attTotal ? round($onTime->count() / $attTotal * 100) : 0,
        ];

        // —— §9.1-6 每日交接完成率（最近 7 个有交接待办的日期）——
        $handoverDays = Todo::query()->where('type', TodoType::HANDOVER)
            ->selectRaw('DATE(created_at) d, COUNT(*) total, SUM(status = ?) done', [TodoStatus::COMPLETED->value])
            ->groupBy('d')->orderByDesc('d')->limit(7)->get()
            ->map(fn ($r) => [
                'date' => $r->d,
                'label' => substr($r->d, 5),
                'is_today' => $r->d === now()->toDateString(),
                'total' => (int) $r->total,
                'done' => (int) $r->done,
                'pct' => $r->total ? round($r->done / $r->total * 100) : 0,
            ])->reverse()->values();

        return $this->ok([
            'year' => $year,
            'volunteer' => $volunteer,
            'training' => $training,
            'safety' => $safety,
            'svc_by_month' => $svcByMonth,
            'train_by_month' => $trByMonth,
            'materials' => [
                'buy_fixed' => $buyFixed,
                'buy_cons' => $buyCons,
                'scrap_fixed' => $scrapFixed,
                'scrap_cons' => $scrapCons,
                'in_use' => $inUseByKind,
                'in_use_total' => array_sum($inUseByKind),
            ],
            'duty' => $duty,
            'handover_days' => $handoverDays,
        ]);
    }

    /**
     * GET /api/work/assess-stats 月度考核统计（与原型 renderAssessStatCard 口径一致）。
     * mode=year：该年度每人全年各月平均分（按各自考核月份数取均值，保留 1 位小数）；
     * mode=month：指定月份每人当月得分。返回该年度有数据的月份列表供前端下拉。
     */
    public function assessStats(Request $request): JsonResponse
    {
        $year = (int) $request->input('year', now()->format('Y'));
        $mode = $request->input('mode', 'year') === 'month' ? 'month' : 'year';

        $yearRows = Assessment::query()->with('user')
            ->where('month', 'like', $year.'-%')->get();
        $months = $yearRows->pluck('month')->unique()->sort()->values();

        $month = (string) $request->input('month', '');
        if ($mode === 'month' && ($month === '' || ! $months->contains($month))) {
            $month = $months->last() ?? '';
        }

        if ($mode === 'month') {
            $rows = $yearRows->where('month', $month)
                ->map(fn ($a) => ['name' => $a->user?->username, 'score' => (float) $a->total])
                ->values();
            $note = "统计口径：{$month} 当月考核得分";
        } else {
            $rows = $yearRows->groupBy('user_id')
                ->map(fn ($g) => [
                    'name' => $g->first()->user?->username,
                    'score' => round($g->avg('total'), 1),
                    'months' => $g->count(),
                ])->values();
            $note = "统计口径：{$year} 年全年各月平均分（按考核月份数取均值）";
        }

        $rows = $rows->sortByDesc('score')->values();

        return $this->ok([
            'year' => $year,
            'mode' => $mode,
            'month' => $month,
            'months' => $months,
            'rows' => $rows,
            'note' => $note,
        ]);
    }

    /** GET /api/work/export-buy-scrap 当年采购 vs 报废（按类型）导出 */
    public function exportBuyScrap(Request $request)
    {
        $year = (int) $request->input('year', now()->format('Y'));

        $buy = MaterialStock::query()->where('direction', 'in')->whereYear('stock_date', $year)
            ->selectRaw('type, SUM(qty) total')->groupBy('type')->pluck('total', 'type');
        $scrapRows = Material::query()->where('scrapped', true)
            ->where(function ($q) use ($year) {
                $q->where('actual_scrap_year', $year)
                    ->orWhere(function ($q2) use ($year) {
                        $q2->whereNull('actual_scrap_year')->whereYear('stock_out_at', $year);
                    });
            })->get(['kind', 'qty']);

        $rows = [
            ['固定资产', (int) ($buy[MaterialKind::FIXED->value] ?? 0), (int) $scrapRows->where('kind', MaterialKind::FIXED)->sum('qty')],
            ['耗材类', (int) ($buy[MaterialKind::CONSUMABLE->value] ?? 0), (int) $scrapRows->where('kind', MaterialKind::CONSUMABLE)->sum('qty')],
        ];
        $rows[] = ['合计', $rows[0][1] + $rows[1][1], $rows[0][2] + $rows[1][2]];

        return $this->xlsxDownload("采购报废统计_{$year}年", ['物资类型', '当年采购数量（件）', '当年报废数量（件）'], $rows);
    }

    /** GET /api/work/export-in-use 宿舍在用物资（按家具种类）导出 */
    public function exportInUse()
    {
        $dormNos = Room::query()->where('type', RoomType::DORM)->pluck('room_no');
        $inUse = Material::query()->whereIn('location', $dormNos)
            ->whereIn('furniture_kind', ['桌椅类', '床类', '柜类'])
            ->selectRaw('furniture_kind, SUM(qty) total')->groupBy('furniture_kind')
            ->pluck('total', 'furniture_kind');

        $rows = [];
        $total = 0;
        foreach (['桌椅类', '床类', '柜类'] as $kind) {
            $n = (int) ($inUse[$kind] ?? 0);
            $rows[] = [$kind, $n];
            $total += $n;
        }
        $rows[] = ['合计', $total];

        return $this->xlsxDownload('宿舍在用物资统计', ['家具种类', '数量（件）'], $rows);
    }
}
