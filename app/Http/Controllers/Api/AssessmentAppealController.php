<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AssessmentAppeal;
use App\Support\ApiResponse;
use App\Support\ExportsExcel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 考核申诉（需求 10.2，与原型「考核申诉」一致）。
 * 数据范围：管理员看全部并可创建/编辑/删除；其他身份仅看本人申诉（只读）。
 * 管理员填写处理结果（result）后，状态自动流转为「已完成」。
 */
class AssessmentAppealController extends Controller
{
    use ApiResponse;
    use ExportsExcel;

    public function index(Request $request): JsonResponse
    {
        $query = AssessmentAppeal::query()->with('user');
        if (! $request->user()->isAdmin()) {
            $query->where('user_id', $request->user()->id);
        }
        if ($name = trim((string) $request->input('name', ''))) {
            $query->whereHas('user', fn ($q) => $q->where('username', 'like', "%{$name}%"));
        }
        if ($month = $request->input('month')) {
            $query->where('month', $month);
        }

        $paginator = $query->orderByDesc('appeal_date')->orderByDesc('id')
            ->paginate((int) $request->input('pageSize', 10));
        $paginator->setCollection($paginator->getCollection()->map(fn ($a) => $this->toRow($a)));

        return $this->page($paginator);
    }

    public function store(Request $request): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return $this->fail(1002, '仅管理员可创建申诉', 403);
        }
        $data = $request->validate($this->rules(), $this->messages());

        $row = AssessmentAppeal::create([
            'user_id' => $data['user_id'],
            'month' => $data['month'],
            'matter' => $data['matter'],
            'appeal_date' => $data['appeal_date'],
            'result' => $data['result'] ?? null,
            'rectify' => $data['rectify'] ?? null,
            'status' => ! empty($data['result']) ? AssessmentAppeal::STATUS_DONE : AssessmentAppeal::STATUS_PENDING,
            'created_by' => $request->user()->id,
        ]);

        return $this->ok($this->toRow($row->refresh()->load('user')), '已创建');
    }

    public function update(Request $request, AssessmentAppeal $appeal): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return $this->fail(1002, '仅管理员可编辑申诉', 403);
        }
        $data = $request->validate($this->rules(), $this->messages());

        $appeal->fill([
            'user_id' => $data['user_id'],
            'month' => $data['month'],
            'matter' => $data['matter'],
            'appeal_date' => $data['appeal_date'],
            'result' => $data['result'] ?? null,
            'rectify' => $data['rectify'] ?? null,
            'status' => ! empty($data['result']) ? AssessmentAppeal::STATUS_DONE : AssessmentAppeal::STATUS_PENDING,
        ])->save();

        return $this->ok($this->toRow($appeal->refresh()->load('user')), '已保存');
    }

    public function destroy(Request $request, AssessmentAppeal $appeal): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return $this->fail(1002, '仅管理员可删除申诉', 403);
        }
        $appeal->delete();

        return $this->ok(null, '已删除');
    }

    /** GET /api/assessment-appeals/export 申诉导出（复用列表筛选） */
    public function export(Request $request)
    {
        $query = AssessmentAppeal::query()->with('user');
        if (! $request->user()->isAdmin()) {
            $query->where('user_id', $request->user()->id);
        }
        if ($name = trim((string) $request->input('name', ''))) {
            $query->whereHas('user', fn ($q) => $q->where('username', 'like', "%{$name}%"));
        }
        if ($month = $request->input('month')) {
            $query->where('month', $month);
        }

        $rows = $query->orderByDesc('appeal_date')->orderByDesc('id')->get()
            ->map(fn ($a) => [
                $a->user?->username,
                $a->month,
                $a->matter,
                $a->appeal_date?->format('Y-m-d H:i'),
                $a->result,
                $a->rectify,
                $a->statusLabel(),
            ])->all();

        return $this->xlsxDownload('考核申诉导出',
            ['员工姓名', '考核月份', '申诉事项', '申诉日期', '处理结果', '自我整改措施', '状态'], $rows);
    }

    private function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'month' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'matter' => ['required', 'string', 'max:1000'],
            'appeal_date' => ['required', 'date'],
            'result' => ['nullable', 'string', 'max:1000'],
            'rectify' => ['nullable', 'string', 'max:500'],
        ];
    }

    private function messages(): array
    {
        return [
            'user_id.required' => '请选择员工',
            'month.required' => '请选择考核月份',
            'month.regex' => '考核月份格式应为 YYYY-MM',
            'matter.required' => '请填写申诉事项',
            'appeal_date.required' => '请选择申诉日期',
        ];
    }

    private function toRow(AssessmentAppeal $a): array
    {
        return [
            'id' => $a->id,
            'user_id' => $a->user_id,
            'name' => $a->user?->username,
            'month' => $a->month,
            'matter' => $a->matter,
            'appeal_date' => $a->appeal_date?->format('Y-m-d H:i'),
            'result' => $a->result,
            'rectify' => $a->rectify,
            'status' => $a->status,
            'status_label' => $a->statusLabel(),
            'created_at' => $a->created_at?->format('Y-m-d H:i'),
            'updated_at' => $a->updated_at?->format('Y-m-d H:i'),
        ];
    }
}
