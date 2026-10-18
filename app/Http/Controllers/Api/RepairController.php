<?php

namespace App\Http\Controllers\Api;

use App\Enums\AppointPeriod;
use App\Enums\RepairCategory;
use App\Enums\RepairStatus;
use App\Enums\TodoStatus;
use App\Enums\TodoType;
use App\Http\Controllers\Controller;
use App\Models\Repair;
use App\Models\Todo;
use App\Models\TodoAssignee;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * 报修管理（需求 §6）
 * 联动（§1.1 / §六）：新增或编辑报修填写「跟修人」→ 自动生成维修待办；
 * 该待办完成后 → 联动本表「维修情况」置「已完成」（见 Todo 模型 updated 事件）。
 */
class RepairController extends Controller
{
    use ApiResponse;

    /** GET /api/repairs 列表（筛选：报修地点 / 报修类别 / 日期） */
    public function index(Request $request): JsonResponse
    {
        $query = Repair::with(['follower', 'reporter']);

        if ($kw = trim((string) $request->input('location', ''))) {
            $query->where('location', 'like', "%{$kw}%");
        }
        if ($category = $request->input('category')) {
            $query->where('category', $category);
        }
        if ($date = $request->input('date')) {
            $query->whereDate('report_date', $date);
        }
        if ($status = $request->input('repair_status')) {
            $query->where('repair_status', $status);
        }

        $paginator = $query->orderByDesc('report_date')->orderByDesc('id')
            ->paginate((int) $request->input('pageSize', 10));

        $paginator->setCollection($paginator->getCollection()->map(fn ($r) => $this->toRow($r)));

        return $this->page($paginator);
    }

    /** GET /api/repairs/options 类别二级联动字典 + 跟修人下拉 + 当前用户字段权限 */
    public function options(Request $request): JsonResponse
    {
        $user = $request->user();

        return $this->ok([
            'categories' => RepairCategory::options(),
            'sub_categories' => RepairCategory::subCategories(),
            'statuses' => RepairStatus::options(),
            'periods' => AppointPeriod::options(),
            'followers' => User::orderBy('username')
                ->get(['id', 'username'])
                ->map(fn ($u) => ['id' => $u->id, 'name' => $u->username])
                ->all(),
            // 维修待办的固定接收人（需求 §6）：维修负责人 + 全部管理员
            'repair_recipients' => User::whereIn('id', User::repairTodoRecipients()->all())
                ->orderBy('username')
                ->pluck('username')
                ->all(),
            // 「跟修人」默认值：维修负责人（需求 §6 / 原型 forms.js L637）
            'default_follower_id' => User::whereIn('username', User::REPAIR_FOLLOWERS)->value('id'),
            'can_edit_repair_status' => $user->canEditRepairStatus(),
            'is_repair_follower' => $user->isRepairFollower(),
        ]);
    }

    /** POST /api/repairs 新增报修 */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user->isRepairFollower()) {
            return $this->fail(1002, '当前账号无新增报修权限（仅可修改维修情况）');
        }

        $data = $request->validate($this->rules(), $this->messages());

        // 非管理员、非维修负责人提交的「维修情况」一律忽略
        if (! $user->canEditRepairStatus()) {
            unset($data['repair_status']);
        }

        $repair = DB::transaction(function () use ($data, $user) {
            $repair = Repair::create($data + [
                'reporter_id' => $user->id,
                'repair_status' => $data['repair_status'] ?? RepairStatus::PENDING->value,
            ]);

            $this->syncRepairTodo($repair, $repair->follower_id, $user->id);

            return $repair;
        });

        return $this->ok($this->toRow($repair->refresh()), $repair->follower_id ? '已添加，已通知维修负责人' : '已添加');
    }

    /** PUT /api/repairs/{repair} 编辑 */
    public function update(Request $request, Repair $repair): JsonResponse
    {
        $user = $request->user();

        // 维修负责人：只能改「维修情况」
        if ($user->isRepairFollower()) {
            $data = $request->validate(['repair_status' => ['required', 'in:pending,processing,done']]);
        } else {
            $data = $request->validate($this->rules(), $this->messages());
            if (! $user->canEditRepairStatus()) {
                unset($data['repair_status']);
            }
        }

        $followerTouched = array_key_exists('follower_id', $data);

        DB::transaction(function () use ($repair, $data, $user, $followerTouched) {
            $repair->fill($data)->save();

            if ($followerTouched) {
                $this->syncRepairTodo($repair, $repair->follower_id, $user->id);
            }
        });

        return $this->ok($this->toRow($repair->refresh()), '已保存');
    }

    /** DELETE /api/repairs/{repair} 删除 */
    public function destroy(Request $request, Repair $repair): JsonResponse
    {
        if ($request->user()->isRepairFollower()) {
            return $this->fail(1002, '当前账号无删除权限（仅可修改维修情况）');
        }

        DB::transaction(function () use ($repair) {
            // 撤销该报修的全部维修待办（含已完成的，避免留下悬空待办）
            Todo::where('type', TodoType::REPAIR)
                ->where('source_type', Repair::class)
                ->where('source_id', $repair->id)
                ->delete();

            $repair->delete();
        });

        return $this->ok(null, '已删除');
    }

    /** GET /api/repairs/export 导出（按搜索条件） */
    public function export(Request $request)
    {
        $query = Repair::with(['follower', 'reporter']);

        if ($kw = trim((string) $request->input('location', ''))) {
            $query->where('location', 'like', "%{$kw}%");
        }
        if ($category = $request->input('category')) {
            $query->where('category', $category);
        }
        if ($date = $request->input('date')) {
            $query->whereDate('report_date', $date);
        }

        $rows = $query->orderByDesc('report_date')->orderBy('id')->get();

        $headers = [
            '日期', '报修地点', '报修类别', '报修项目', '问题描述', '维修日期', '预约时间',
            '同意代修', '报修人联系方式', '上报平台', '跟修人', '维修情况', '创建时间', '修改时间',
        ];

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        foreach ($headers as $i => $h) {
            $sheet->setCellValue($this->colLetter($i + 1).'1', $h);
        }
        $lastCol = $this->colLetter(count($headers));
        $headerStyle = $sheet->getStyle("A1:{$lastCol}1");
        $headerStyle->getFont()->setBold(true);
        $headerStyle->getFill()->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFD9E1F2');

        foreach ($rows as $r => $row) {
            $values = [
                $row->report_date?->format('Y-m-d'),
                $row->location,
                $row->category->label(),
                $row->sub_category,
                $row->description,
                $row->repair_date?->format('Y-m-d'),
                $row->appoint_period ? $row->appoint_period->label().' '.$row->appoint_period->timeRange() : null,
                $row->is_agree_proxy ? '是' : '否',
                $row->contact,
                $row->is_reported_platform ? '是' : '否',
                $row->follower?->username,
                $row->repair_status->label(),
                $row->created_at?->format('Y-m-d H:i'),
                $row->updated_at?->format('Y-m-d H:i'),
            ];
            foreach ($values as $c => $v) {
                $sheet->setCellValue($this->colLetter($c + 1).($r + 2), $v);
            }
        }

        foreach (range(1, count($headers)) as $c) {
            $sheet->getColumnDimension($this->colLetter($c))->setWidth(16);
        }
        $sheet->getColumnDimension('E')->setWidth(32);

        $filename = '报修管理导出_'.now()->format('Ymd_His').'.xlsx';
        ob_start();
        (new Xlsx($spreadsheet))->save('php://output');
        $content = ob_get_clean();

        return response($content, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename*=UTF-8''".rawurlencode($filename),
        ]);
    }

    /**
     * 同步维修待办：
     * - 有跟修人 → 没有待办则新建，已有则重设接收人
     * - 无跟修人 → 撤销尚未完成的待办
     *
     * 接收人固定为「维修负责人 + 全部管理员」（需求 §6），与所选「跟修人」无关：
     * 跟修人只作分工记录，待办由维修负责人跟进处理。
     */
    private function syncRepairTodo(Repair $repair, ?int $followerId, ?int $creatorId): void
    {
        $todo = Todo::where('type', TodoType::REPAIR)
            ->where('source_type', Repair::class)
            ->where('source_id', $repair->id)
            ->where('status', TodoStatus::PENDING)
            ->first();

        if (! $followerId) {
            $todo?->delete();

            return;
        }

        if (! $todo) {
            $todo = Todo::create([
                'type' => TodoType::REPAIR,
                'title' => '维修待办：'.$repair->location.' '.$repair->sub_category,
                'content' => sprintf(
                    '报修地点：%s%s报修类别：%s-%s%s问题描述：%s%s跟修人：%s%s维修日期：%s  预约时间：%s',
                    $repair->location,
                    PHP_EOL,
                    $repair->category->label(),
                    $repair->sub_category,
                    PHP_EOL,
                    $repair->description,
                    PHP_EOL,
                    $repair->follower?->username ?? '未指定',
                    PHP_EOL,
                    $repair->repair_date?->format('Y-m-d') ?? '待定',
                    $repair->appoint_period?->label() ?? '待定',
                ),
                'status' => TodoStatus::PENDING,
                'source_type' => Repair::class,
                'source_id' => $repair->id,
                'created_by' => $creatorId,
            ]);
        }

        // 重设接收人：维修负责人 + 全部管理员
        TodoAssignee::where('todo_id', $todo->id)->delete();
        foreach (User::repairTodoRecipients() as $userId) {
            TodoAssignee::create(['todo_id' => $todo->id, 'user_id' => $userId]);
        }
    }

    private function rules(): array
    {
        return [
            'report_date' => ['required', 'date'],
            'location' => ['required', 'string', 'max:100'],
            'category' => ['required', 'in:water,wood,electric,ac,other'],
            'sub_category' => ['required', 'string', 'max:50'],
            'description' => ['required', 'string', 'max:1000'],
            'repair_date' => ['nullable', 'date'],
            'appoint_period' => ['required', 'in:am,pm'],
            'is_agree_proxy' => ['required', 'boolean'],
            'contact' => ['required', 'string', 'max:50'],
            'is_reported_platform' => ['required', 'boolean'],
            'follower_id' => ['nullable', 'exists:users,id'],
            'repair_status' => ['nullable', 'in:pending,processing,done'],
        ];
    }

    private function messages(): array
    {
        return [
            'report_date.required' => '请选择报修日期',
            'location.required' => '请填写报修地点',
            'category.required' => '请选择报修类别',
            'sub_category.required' => '请选择报修项目',
            'description.required' => '请填写问题描述',
            'appoint_period.required' => '请选择预约时间',
            'is_agree_proxy.required' => '请选择是否同意代修',
            'contact.required' => '请填写报修人联系方式',
            'is_reported_platform.required' => '请选择是否上报平台',
            'follower_id.exists' => '跟修人不存在',
        ];
    }

    private function toRow(Repair $repair): array
    {
        return [
            'id' => $repair->id,
            'report_date' => $repair->report_date?->format('Y-m-d'),
            'location' => $repair->location,
            'category' => $repair->category->value,
            'category_label' => $repair->category->label(),
            'sub_category' => $repair->sub_category,
            'description' => $repair->description,
            'repair_date' => $repair->repair_date?->format('Y-m-d'),
            'appoint_period' => $repair->appoint_period?->value,
            'appoint_text' => $repair->appoint_period
                ? $repair->appoint_period->label().' '.$repair->appoint_period->timeRange()
                : null,
            'is_agree_proxy' => $repair->is_agree_proxy,
            'agree_text' => $repair->is_agree_proxy ? '是' : '否',
            'contact' => $repair->contact,
            'is_reported_platform' => $repair->is_reported_platform,
            'report_text' => $repair->is_reported_platform ? '是' : '否',
            'follower_id' => $repair->follower_id,
            'follower' => $repair->follower?->username,
            'reporter' => $repair->reporter?->username,
            'repair_status' => $repair->repair_status->value,
            'repair_status_label' => $repair->repair_status->label(),
            'repair_status_type' => $repair->repair_status->tagType(),
            'created_at' => $repair->created_at?->format('Y-m-d H:i'),
            'updated_at' => $repair->updated_at?->format('Y-m-d H:i'),
        ];
    }

    private function colLetter(int $column): string
    {
        $letter = '';
        while ($column > 0) {
            $mod = ($column - 1) % 26;
            $letter = chr(65 + $mod).$letter;
            $column = intdiv($column - $mod, 26);
        }

        return $letter;
    }
}
