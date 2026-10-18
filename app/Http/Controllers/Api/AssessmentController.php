<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\User;
use App\Support\ApiResponse;
use App\Support\AssessmentDict;
use App\Support\ExportsExcel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 月度考核（需求 10.1，与原型「考核管理 · 月度考核」一致）。
 * 数据范围：管理员看全部并可创建/修改/删除；其他身份仅看本人考核记录（只读）。
 * total / grade 一律由后端按字典重算，不采信前端传值。
 */
class AssessmentController extends Controller
{
    use ApiResponse;
    use ExportsExcel;

    public function index(Request $request): JsonResponse
    {
        $query = Assessment::query()->with(['user', 'supervisor']);

        // 数据范围：非管理员仅本人
        if (! $request->user()->isAdmin()) {
            $query->where('user_id', $request->user()->id);
        }
        if ($name = trim((string) $request->input('name', ''))) {
            $query->whereHas('user', fn ($q) => $q->where('username', 'like', "%{$name}%"));
        }
        if ($month = $request->input('month')) {
            $query->where('month', $month);
        }
        if ($grade = $request->input('grade')) {
            $query->where('grade', $grade);
        }

        $paginator = $query->orderByDesc('month')->orderByDesc('id')
            ->paginate((int) $request->input('pageSize', 10));
        $paginator->setCollection($paginator->getCollection()->map(fn ($a) => $this->toRow($a)));

        return $this->page($paginator);
    }

    /** 字典：考核模块 / 扣分细则 / 加分项 / 等级规则（评分表单渲染依据，登录即可） */
    public function options(): JsonResponse
    {
        return $this->ok([
            'modules' => AssessmentDict::MODULES,
            'deductions' => AssessmentDict::DEDUCTIONS,
            'bonuses' => AssessmentDict::BONUSES,
            'grade_rules' => AssessmentDict::GRADE_RULES,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return $this->fail(1002, '仅管理员可创建考核', 403);
        }
        $data = $request->validate($this->rules(), $this->messages());

        $user = User::findOrFail($data['user_id']);
        if (Assessment::where('user_id', $user->id)->where('month', $data['month'])->exists()) {
            return $this->fail(4001, "该员工「{$data['month']}」的考核已存在，请勿重复创建，可在列表中修改该条记录");
        }

        $items = $this->normalizeItems($data['items'] ?? []);
        $total = Assessment::computeTotal($items);

        $row = Assessment::create([
            'user_id' => $user->id,
            'month' => $data['month'],
            'assess_date' => $data['assess_date'],
            'supervisor_id' => $data['supervisor_id'] ?? null,
            'items' => $items,
            'total' => $total,
            'grade' => AssessmentDict::gradeOf($total),
            'remark' => $data['remark'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        return $this->ok($this->toRow($row->refresh()->load(['user', 'supervisor'])), '已创建');
    }

    public function update(Request $request, Assessment $assessment): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return $this->fail(1002, '仅管理员可修改考核', 403);
        }
        $data = $request->validate([
            'supervisor_id' => ['nullable', 'integer', 'exists:users,id'],
            'assess_date' => ['required', 'date'],
            'items' => ['nullable', 'array', 'size:'.count(AssessmentDict::MODULES)],
            'remark' => ['nullable', 'string', 'max:500'],
        ], $this->messages());

        $items = $this->normalizeItems($data['items'] ?? $assessment->items ?? []);
        $total = Assessment::computeTotal($items);

        $assessment->fill([
            'supervisor_id' => $data['supervisor_id'] ?? $assessment->supervisor_id,
            'assess_date' => $data['assess_date'],
            'items' => $items,
            'total' => $total,
            'grade' => AssessmentDict::gradeOf($total),
            'remark' => $data['remark'] ?? null,
        ])->save();

        return $this->ok($this->toRow($assessment->refresh()->load(['user', 'supervisor'])), '已保存');
    }

    public function destroy(Request $request, Assessment $assessment): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return $this->fail(1002, '仅管理员可删除考核', 403);
        }
        $assessment->delete();

        return $this->ok(null, '已删除');
    }

    /** GET /api/assessments/export 考核导出（复用列表筛选，管理员导出全部、其他人仅本人） */
    public function export(Request $request)
    {
        $query = Assessment::query()->with(['user', 'supervisor']);
        if (! $request->user()->isAdmin()) {
            $query->where('user_id', $request->user()->id);
        }
        if ($name = trim((string) $request->input('name', ''))) {
            $query->whereHas('user', fn ($q) => $q->where('username', 'like', "%{$name}%"));
        }
        if ($month = $request->input('month')) {
            $query->where('month', $month);
        }
        if ($grade = $request->input('grade')) {
            $query->where('grade', $grade);
        }

        $rows = $query->orderByDesc('month')->orderByDesc('id')->get()
            ->map(fn ($a) => [
                $a->user?->username,
                $a->user?->employee_no,
                $a->user ? AssessmentDict::postOf($a->user) : null,
                $a->month,
                (float) $a->total,
                $a->grade,
                $a->supervisor?->username,
                $a->assess_date?->format('Y-m-d'),
                $a->remark,
            ])->all();

        return $this->xlsxDownload('月度考核导出',
            ['员工姓名', '员工编号', '所属岗位', '考核月份', '最终得分', '考核等级', '直属主管', '考核日期', '备注'], $rows);
    }

    private function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'month' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'assess_date' => ['required', 'date'],
            'supervisor_id' => ['nullable', 'integer', 'exists:users,id'],
            'items' => ['nullable', 'array', 'size:'.count(AssessmentDict::MODULES)],
            'items.*.ded' => ['nullable', 'numeric', 'min:0', 'max:999'],
            'items.*.bon' => ['nullable', 'numeric', 'min:0', 'max:999'],
            'items.*.note' => ['nullable', 'string', 'max:500'],
            'remark' => ['nullable', 'string', 'max:500'],
        ];
    }

    private function messages(): array
    {
        return [
            'user_id.required' => '请选择被考核员工',
            'month.required' => '请选择考核月份',
            'month.regex' => '考核月份格式应为 YYYY-MM',
            'assess_date.required' => '请选择考核日期',
            'items.size' => '评分明细须包含全部考核模块',
        ];
    }

    /** 规范化 items：按字典模块数补齐，ded/bon 取非负浮点，note 裁剪 */
    private function normalizeItems(array $items): array
    {
        $out = [];
        foreach (AssessmentDict::MODULES as $i => $m) {
            $it = $items[$i] ?? [];
            $out[] = [
                'ded' => max(0, (float) ($it['ded'] ?? 0)),
                'bon' => max(0, (float) ($it['bon'] ?? 0)),
                'note' => trim((string) ($it['note'] ?? '')),
            ];
        }

        return $out;
    }

    private function toRow(Assessment $a): array
    {
        $items = $a->items ?? [];
        $totalDed = round(array_sum(array_map(fn ($it) => (float) ($it['ded'] ?? 0), $items)), 1);
        $totalBon = round(array_sum(array_map(fn ($it) => (float) ($it['bon'] ?? 0), $items)), 1);

        return [
            'id' => $a->id,
            'user_id' => $a->user_id,
            'name' => $a->user?->username,
            'job_no' => $a->user?->employee_no,
            'post' => $a->user ? AssessmentDict::postOf($a->user) : null,
            'month' => $a->month,
            'assess_date' => $a->assess_date?->format('Y-m-d'),
            'supervisor_id' => $a->supervisor_id,
            'supervisor' => $a->supervisor?->username,
            'items' => $items,
            'total_ded' => $totalDed,
            'total_bon' => $totalBon,
            'total' => (float) $a->total,
            'grade' => $a->grade,
            'remark' => $a->remark,
            'created_at' => $a->created_at?->format('Y-m-d H:i'),
            'updated_at' => $a->updated_at?->format('Y-m-d H:i'),
        ];
    }
}
