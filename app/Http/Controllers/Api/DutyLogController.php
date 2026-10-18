<?php

namespace App\Http\Controllers\Api;

use App\Enums\TodoStatus;
use App\Enums\TodoType;
use App\Http\Controllers\Controller;
use App\Models\DutyLog;
use App\Models\Schedule;
use App\Models\Todo;
use App\Models\TodoAssignee;
use App\Models\User;
use App\Support\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * 值班日志（需求 2-2：扁平单表结构，一条记录 = 日期 + 分类 + 分类字段详情 + 创建人 + 状态）
 * 分类：phone 电话接听 / visit 咨询接待 / exception 异常情况 / service 服务保障
 */
class DutyLogController extends Controller
{
    use ApiResponse;

    /** 分类元数据：标签 + 字段定义（label / 是否必填 / 类型） */
    private const CATEGORIES = [
        'phone' => [
            'label' => '电话接听',
            'fields' => [
                'caller' => ['label' => '来电人/单位', 'required' => true],
                'need_callback' => ['label' => '是否需要回电', 'required' => true, 'type' => 'bool'],
                'contact' => ['label' => '联系方式', 'required' => true],
                'callback_done' => ['label' => '是否已回电', 'required' => true, 'type' => 'bool'],
            ],
        ],
        'visit' => [
            'label' => '咨询接待',
            'fields' => [
                'visitor' => ['label' => '来访人/单位', 'required' => true],
                'matter' => ['label' => '咨询事项', 'required' => true],
                'need_transfer' => ['label' => '是否需要移交其他部门', 'required' => true, 'type' => 'bool'],
                'transfer_to' => ['label' => '移交部门/联系人', 'required' => false],
                'done' => ['label' => '是否完成', 'required' => true, 'type' => 'bool'],
            ],
        ],
        'exception' => [
            'label' => '异常情况',
            'fields' => [
                'event_desc' => ['label' => '事件描述', 'required' => true, 'type' => 'textarea'],
                'images' => ['label' => '图片', 'required' => false, 'type' => 'images'],
                'report' => ['label' => '是否上报', 'required' => true, 'type' => 'bool'],
                'process' => ['label' => '处置情况', 'required' => true],
            ],
        ],
        'service' => [
            'label' => '服务保障',
            'fields' => [
                'project' => ['label' => '保障项目', 'required' => true],
                'progress' => ['label' => '进度情况', 'required' => true],
                'publish' => ['label' => '提示信息发布', 'required' => true, 'type' => 'publish'],
                'done' => ['label' => '是否完成', 'required' => true, 'type' => 'bool'],
            ],
        ],
    ];

    public function index(Request $request)
    {
        $query = DutyLog::with('creator');

        // 日期筛选（原型为单日期；同时兼容日期范围）
        if ($request->filled('date')) {
            $query->where('log_date', $request->input('date'));
        } elseif ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('log_date', [$request->input('start_date'), $request->input('end_date')]);
        }

        if ($request->filled('category') && isset(self::CATEGORIES[$request->input('category')])) {
            $query->where('category', $request->input('category'));
        }

        $paginator = $query->orderByDesc('log_date')
            ->orderByDesc('id')
            ->paginate((int) $request->input('pageSize', 10));

        $logs = $paginator->getCollection();
        $targets = $this->handoverTargetsMap($logs->pluck('id'));
        $dutyUsers = $this->dutyUsersMap($logs);
        $logs->transform(fn ($log) => $this->toRow(
            $log,
            $targets[$log->id] ?? '',
            $dutyUsers[$log->log_date->format('Y-m-d')] ?? []
        ));

        return $this->page($paginator);
    }

    public function store(Request $request)
    {
        [$data, $detail] = $this->validateLog($request);

        $creatorId = $data['creator_id'] ?? $request->user()->id;
        if (! $request->user()->isAdmin() && $creatorId != $request->user()->id) {
            return $this->fail(1002, '无权以他人名义记录日志');
        }

        $log = DutyLog::create([
            'log_date' => $data['log_date'],
            'category' => $data['category'],
            'detail' => $detail,
            'creator_id' => $creatorId,
            'status' => $data['status'],
        ]);

        return $this->ok($this->toRow($log->load('creator')), '新增成功');
    }

    public function update(Request $request, DutyLog $dutyLog)
    {
        [$data, $detail] = $this->validateLog($request);

        if (! $request->user()->isAdmin() && $dutyLog->creator_id != $request->user()->id) {
            return $this->fail(1002, '无权修改他人日志');
        }

        $dutyLog->update([
            'log_date' => $data['log_date'],
            'category' => $data['category'],
            'detail' => $detail,
            'status' => $data['status'],
        ]);

        return $this->ok($this->toRow($dutyLog->load('creator')), '更新成功');
    }

    public function destroy(Request $request, DutyLog $dutyLog)
    {
        if (! $request->user()->isAdmin() && $dutyLog->creator_id != $request->user()->id) {
            return $this->fail(1002, '无权删除他人日志');
        }

        $dutyLog->delete();

        return $this->ok(null, '删除成功');
    }

    /**
     * 交接：标记已交接 + 给次日值班人生成待办（原型：已交接或已完成则置灰不可再点）
     */
    public function handover(Request $request, DutyLog $dutyLog)
    {
        $user = $request->user();

        // 交接是记录人/当班人的责任，交接接收人只能「完成」，不能再往下转
        if ($dutyLog->handoff_done || $dutyLog->status === 'done') {
            return $this->fail(3002, $this->handoverBlockedReason($dutyLog));
        }

        if (! $this->canHandover($dutyLog, $user)) {
            return $this->fail(1002, '只有日志创建人、当日当班人或管理员可以交接');
        }

        if ($dutyLog->handoff_done || $dutyLog->status === 'done') {
            return $this->fail(3002, $this->handoverBlockedReason($dutyLog));
        }

        $noticeMessage = DB::transaction(function () use ($dutyLog) {
            $dutyLog->update([
                'handoff_done' => true,
                'handed_over_at' => now(),
            ]);

            // 待办派发给次日排班人员；次日无人排班时兜底派给全体管理员
            $nextDay = Carbon::parse($dutyLog->log_date)->addDay()->format('Y-m-d');
            $nextUsers = Schedule::with('user')
                ->where('date', $nextDay)
                ->get()
                ->pluck('user')
                ->filter()
                ->unique('id')
                ->values();

            $fallbackAdmins = false;
            if ($nextUsers->isEmpty()) {
                $fallbackAdmins = true;
                $nextUsers = User::where('identity', 'admin')->get();
            }
            $notified = $nextUsers->pluck('username')->filter()->implode('、');
            $noticeMessage = $fallbackAdmins
                ? '次日无人排班，已通知全体管理员：'.($notified ?: '无')
                : '交接完成，已通知次日值班人：'.($notified ?: '无');

            $todo = Todo::create([
                'type' => TodoType::HANDOVER,
                'title' => sprintf(
                    '值班日志交接：%s %s（%s）',
                    $dutyLog->log_date->format('Y-m-d'),
                    self::CATEGORIES[$dutyLog->category]['label'],
                    $dutyLog->creator?->username ?? '未知'
                ),
                'content' => sprintf(
                    '日志编号：#%d%s创建人：%s%s交接时间：%s%s值班日志已交接，请查阅处理后在待办中点「完成」。',
                    $dutyLog->id,
                    PHP_EOL,
                    $dutyLog->creator?->username ?? '—',
                    PHP_EOL,
                    now()->format('Y-m-d H:i'),
                    PHP_EOL,
                ),
                'status' => TodoStatus::PENDING,
                'source_type' => DutyLog::class,
                'source_id' => $dutyLog->id,
                'created_by' => $dutyLog->creator_id,
            ]);

            foreach ($nextUsers as $u) {
                TodoAssignee::create(['todo_id' => $todo->id, 'user_id' => $u->id]);
            }

            return $noticeMessage;
        });

        return $this->ok($this->toRow($dutyLog->load('creator')), $noticeMessage ?: '交接完成');
    }

    /**
     * 图片上传（异常情况分类，jpg/png，单张 ≤ 5MB，最多 9 张由前端 limit + 校验保证）
     */
    public function upload(Request $request)
    {
        $request->validate([
            'file' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
        ], [
            'file.required' => '请选择要上传的图片',
            'file.image' => '只能上传图片文件',
            'file.mimes' => '仅支持 jpg / png 格式',
            'file.max' => '单张图片不能超过 5MB',
        ]);

        $path = $request->file('file')->store('dutylog', 'local');
        $name = basename($path);

        return $this->ok(['name' => $name, 'url' => "/api/duty-logs/image?name={$name}"], '上传成功');
    }

    /**
     * 图片读取（?name=xxx.png；仅允许 dutylog 目录下合法文件名，防路径穿越）
     * 注意：路由刻意不带 .png 后缀——php -S 内置服务器对带扩展名路径不转发 Laravel
     */
    public function file(Request $request)
    {
        $name = (string) $request->input('name', '');
        if (! preg_match('/^[A-Za-z0-9_\-]{10,80}\.(jpg|jpeg|png)$/', $name)) {
            abort(404);
        }

        $disk = Storage::disk('local');
        if (! $disk->exists("dutylog/{$name}")) {
            abort(404);
        }

        return response()->file($disk->path("dutylog/{$name}"), ['Cache-Control' => 'private, max-age=86400']);
    }

    /**
     * 分类字段元数据（前端渲染表单面板用）
     */
    public function schema()
    {
        return $this->ok(self::CATEGORIES);
    }

    private function validateLog(Request $request): array
    {
        $data = $request->validate([
            'log_date' => ['required', 'date'],
            'category' => ['required', 'in:phone,visit,exception,service'],
            'status' => ['required', 'in:pending,done'],
            'creator_id' => ['nullable', 'integer', 'exists:users,id'],
        ], [
            'log_date.required' => '请选择日期',
            'category.in' => '分类无效',
            'status.in' => '状态无效',
        ]);

        // 按分类校验 detail 字段
        $rules = [];
        $messages = [];
        foreach (self::CATEGORIES[$data['category']]['fields'] as $key => $field) {
            if (($field['type'] ?? null) === 'images') {
                $rules["detail.{$key}"] = ['nullable', 'array', 'max:9'];
                $rules["detail.{$key}.*"] = ['string', 'regex:/^[A-Za-z0-9_\-]{10,80}\.(jpg|jpeg|png)$/'];
                $messages["detail.{$key}.max"] = '图片最多 9 张';

                continue;
            }
            if ($field['required']) {
                $rules["detail.{$key}"] = ['required'];
                $messages["detail.{$key}.required"] = "请填写{$field['label']}";
            } else {
                $rules["detail.{$key}"] = ['nullable'];
            }
        }
        $detailInput = $request->validate($rules, $messages)['detail'] ?? [];

        // 只保留该分类定义过的字段
        $detail = [];
        foreach (self::CATEGORIES[$data['category']]['fields'] as $key => $field) {
            $detail[$key] = $detailInput[$key] ?? null;
        }

        return [$data, $detail];
    }

    /** 批量取各日志日期的当班人员 id，避免列表 N+1 */
    private function dutyUsersMap($logs): array
    {
        $dates = $logs->map(fn ($log) => $log->log_date->format('Y-m-d'))->unique()->all();
        if (! $dates) {
            return [];
        }

        return Schedule::whereIn('date', $dates)
            ->get()
            ->groupBy(fn ($s) => $s->date->format('Y-m-d'))
            ->map(fn ($g) => $g->pluck('user_id')->filter()->unique()->values()->all())
            ->all();
    }

    /**
     * 谁能交接这条日志：创建人 / 管理员 / 该日志日期的当班人员（代班场景）
     * 交接接收人不在其中——他只能「完成」，不能再往下转
     */
    private function canHandover(DutyLog $log, ?User $user, ?array $dutyUserIds = null): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->isAdmin() || $log->creator_id === $user->id) {
            return true;
        }

        if ($dutyUserIds === null) {
            return Schedule::where('date', $log->log_date->format('Y-m-d'))
                ->where('user_id', $user->id)
                ->exists();
        }

        return in_array($user->id, $dutyUserIds, true);
    }

    /** 批量取各日志最近一次交接的接收人，避免列表 N+1 */
    private function handoverTargetsMap($ids): array
    {
        if ($ids->isEmpty()) {
            return [];
        }

        $todos = Todo::with('assignees.user')
            ->where('type', TodoType::HANDOVER)
            ->where('source_type', DutyLog::class)
            ->whereIn('source_id', $ids->all())
            ->orderBy('id')
            ->get()
            ->keyBy('source_id'); // 同 id 多条时保留最新一条

        $map = [];
        foreach ($ids as $id) {
            $map[$id] = $this->targetsOf($todos->get($id));
        }

        return $map;
    }

    /** 单条日志的交接接收人姓名串 */
    private function handoverTargets(DutyLog $log): string
    {
        $todo = Todo::with('assignees.user')
            ->where('type', TodoType::HANDOVER)
            ->where('source_type', DutyLog::class)
            ->where('source_id', $log->id)
            ->orderByDesc('id')
            ->first();

        return $this->targetsOf($todo);
    }

    private function targetsOf(?Todo $todo): string
    {
        if (! $todo) {
            return '';
        }

        return $todo->assignees
            ->map(fn ($a) => $a->user?->username)
            ->filter()
            ->implode('、');
    }

    /** 不可交接的原因（用于置灰提示与接口报错） */
    private function handoverBlockedReason(DutyLog $log): string
    {
        if ($log->status === 'done') {
            return '该日志已完成，无需交接';
        }

        $to = $this->handoverTargets($log);

        return sprintf(
            '该日志已于 %s 交接给%s，等待其确认，无需重复交接',
            $log->handed_over_at?->format('m-d H:i') ?? '—',
            $to ?: '次日值班人'
        );
    }

    private function toRow(DutyLog $log, ?string $handoverTo = null, ?array $dutyUserIds = null): array
    {
        // 分类字段详情：平铺成 label/value 列表（原型 dl-kv 形式）
        $meta = self::CATEGORIES[$log->category] ?? null;
        $fields = [];
        if ($meta) {
            foreach ($meta['fields'] as $key => $field) {
                $value = $log->detail[$key] ?? null;
                $type = $field['type'] ?? null;
                if ($type === 'images') {
                    $images = is_array($value) ? $value : [];
                    $fields[] = [
                        'label' => $field['label'],
                        'value' => count($images) ? count($images).'张图片' : null,
                        'images' => array_map(fn ($n) => "/api/duty-logs/image?name={$n}", $images),
                    ];

                    continue;
                }
                if ($type === 'bool') {
                    $value = is_null($value) ? null : ($value ? '是' : '否');
                } elseif ($type === 'publish') {
                    $value = is_null($value) ? null : ($value ? '已发布' : '未发布');
                }
                $fields[] = ['label' => $field['label'], 'value' => $value];
            }
        }

        return [
            'id' => $log->id,
            'log_date' => $log->log_date->format('Y-m-d'),
            'category' => $log->category,
            'category_label' => $meta['label'] ?? $log->category,
            'detail' => $log->detail,
            'fields' => $fields,
            'creator_id' => $log->creator_id,
            'creator' => $log->creator?->username,
            'status' => $log->status,
            // 三态：已完成 / 已交接（待接收人确认）/ 未完成
            'status_label' => $log->status === 'done' ? '已完成' : ($log->handoff_done ? '已交接' : '未完成'),
            'handoff_done' => $log->handoff_done,
            'handed_over_at' => $log->handed_over_at?->format('Y-m-d H:i:s'),
            'handover_to' => $handoverTo ?? $this->handoverTargets($log),
            'can_handover' => ! $log->handoff_done
                && $log->status !== 'done'
                && $this->canHandover($log, auth()->user(), $dutyUserIds),
            'created_at' => $log->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $log->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
