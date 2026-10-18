<?php

namespace App\Http\Controllers\Api;

use App\Enums\ApproveStatus;
use App\Enums\LeaveType;
use App\Enums\ScheduleStatus;
use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Models\Leave;
use App\Models\LeaveBalance;
use App\Models\Schedule;
use App\Models\User;
use App\Support\ApiResponse;
use App\Support\ExportsExcel;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * 请假列表（需求 §4.1）
 * 审批通过 → 该人请假区间内的排班状态置「请假」（联动 §3.1 / §3.3）
 */
class LeaveController extends Controller
{
    use ApiResponse;
    use ExportsExcel;

    /** GET /api/leaves 列表（筛选：请假人 / 状态 / 申请时间） */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Leave::with(['user', 'substitute', 'approver']);

        // 普通用户只看自己的请假
        if (! $user->isAdmin()) {
            $query->where('user_id', $user->id);
        } elseif ($request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        if ($kw = trim((string) $request->input('keyword', ''))) {
            $query->whereHas('user', fn ($q) => $q->where('username', 'like', "%{$kw}%"));
        }

        if ($status = $request->input('status')) {
            $query->where('approve_status', $status);
        }

        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }

        if ($date = $request->input('applied_date')) {
            $query->whereDate('applied_at', $date);
        }

        $paginator = $query->orderByDesc('applied_at')
            ->paginate((int) $request->input('pageSize', 10));

        $paginator->setCollection($paginator->getCollection()->map(fn ($l) => $this->toRow($l)));

        return $this->page($paginator);
    }

    /** GET /api/leaves/options 下拉选项（人员列表 / 类型 / 状态） */
    public function options(): JsonResponse
    {
        return $this->ok([
            'users' => User::orderBy('employee_no')->get(['id', 'username', 'employee_no']),
            'types' => LeaveType::options(),
            'statuses' => ApproveStatus::options(),
        ]);
    }

    /** GET /api/leaves/annual-remain?user_id= 查询某人剩余年假（原型：选「年假」时展示） */
    public function annualRemain(Request $request): JsonResponse
    {
        $userId = (int) $request->input('user_id', 0);
        $balance = LeaveBalance::where('user_id', $userId)->first();

        return $this->ok([
            'has_record' => (bool) $balance,
            'total_days' => $balance ? (float) $balance->total_days : null,
            'remain_days' => $balance ? (float) $balance->remain_days : null,
        ]);
    }

    /** GET /api/leaves/export 请假导出（复用列表筛选） */
    public function export(Request $request)
    {
        $user = $request->user();
        $query = Leave::with(['user', 'substitute', 'approver']);

        if (! $user->isAdmin()) {
            $query->where('user_id', $user->id);
        } elseif ($request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }
        if ($kw = trim((string) $request->input('keyword', ''))) {
            $query->whereHas('user', fn ($q) => $q->where('username', 'like', "%{$kw}%"));
        }
        if ($status = $request->input('status')) {
            $query->where('approve_status', $status);
        }
        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }
        if ($date = $request->input('applied_date')) {
            $query->whereDate('applied_at', $date);
        }

        $rows = $query->orderByDesc('applied_at')->get()
            ->map(fn ($l) => [
                $l->applied_at?->format('Y-m-d H:i'),
                $l->user?->username,
                $l->type->label(),
                $l->start_date?->format('Y-m-d'),
                $l->end_date?->format('Y-m-d'),
                $l->days(),
                $l->reason,
                $l->substitute?->username,
                $l->approve_status->label(),
                $l->approver?->username,
                $l->approved_at?->format('Y-m-d H:i'),
            ])->all();

        return $this->xlsxDownload('请假导出', [
            '申请时间', '申请人', '类型', '开始日期', '结束日期', '天数', '事由',
            '顶班人', '状态', '审批人', '审批时间',
        ], $rows);
    }

    /** POST /api/leaves 申请请假 */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'applied_at' => ['required', 'date'],
            'type' => ['required', 'in:annual,personal,sick'],
            'reason' => ['nullable', 'string', 'max:500'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'substitute_user_id' => ['nullable', 'exists:users,id'],
            'images' => ['nullable', 'array', 'max:9'],
            'images.*' => ['string'],
        ], [
            'user_id.required' => '请选择请假人',
            'applied_at.required' => '请选择申请时间',
            'type.required' => '请选择类型',
            'start_date.required' => '请选择开始时间',
            'end_date.required' => '请选择结束时间',
            'end_date.after_or_equal' => '结束时间不能早于开始时间',
            'images.max' => '图片最多 9 张',
        ]);

        // 普通用户只能给自己请假
        if (! $user->isAdmin() && (int) $data['user_id'] !== $user->id) {
            return $this->fail(3001, '只能为自己申请请假');
        }

        $leave = DB::transaction(function () use ($data, $user) {
            $leave = Leave::create([
                'user_id' => $data['user_id'],
                'applied_at' => Carbon::parse($data['applied_at']),
                'type' => $data['type'],
                'reason' => $data['reason'] ?? null,
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'substitute_user_id' => $data['substitute_user_id'] ?? null,
                'approver_id' => $this->defaultApproverId(),
                'approve_status' => ApproveStatus::PENDING,
            ]);

            $this->syncImages($leave, $data['images'] ?? [], $user);

            return $leave;
        });

        return $this->ok($this->toRow($leave), '已提交申请');
    }

    /** PUT /api/leaves/{leave} 编辑 */
    public function update(Request $request, Leave $leave): JsonResponse
    {
        $user = $request->user();

        if (! $user->isAdmin() && $leave->user_id !== $user->id) {
            return $this->fail(3001, '只能修改自己的请假申请');
        }

        if ($leave->approve_status !== ApproveStatus::PENDING) {
            return $this->fail(3002, '已审批的申请不可修改');
        }

        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'applied_at' => ['required', 'date'],
            'type' => ['required', 'in:annual,personal,sick'],
            'reason' => ['nullable', 'string', 'max:500'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'substitute_user_id' => ['nullable', 'exists:users,id'],
            'images' => ['nullable', 'array', 'max:9'],
            'images.*' => ['string'],
        ], [
            'end_date.after_or_equal' => '结束时间不能早于开始时间',
            'images.max' => '图片最多 9 张',
        ]);

        DB::transaction(function () use ($leave, $data, $user) {
            $leave->fill([
                'user_id' => $data['user_id'],
                'applied_at' => Carbon::parse($data['applied_at']),
                'type' => $data['type'],
                'reason' => $data['reason'] ?? null,
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'substitute_user_id' => $data['substitute_user_id'] ?? null,
            ])->save();

            $this->syncImages($leave, $data['images'] ?? [], $user);
        });

        return $this->ok($this->toRow($leave->refresh()), '已保存');
    }

    /** DELETE /api/leaves/{leave} 删除（已审批通过的先撤销排班联动与年假扣减） */
    public function destroy(Request $request, Leave $leave): JsonResponse
    {
        $user = $request->user();

        if (! $user->isAdmin() && $leave->user_id !== $user->id) {
            return $this->fail(3001, '只能删除自己的请假申请');
        }

        DB::transaction(function () use ($leave) {
            $this->revert($leave);
            Attachment::where('related_type', 'leave')->where('related_id', $leave->id)->delete();
            $leave->delete();
        });

        return $this->ok(null, '已删除');
    }

    /** POST /api/leaves/{leave}/approve 同意 */
    public function approve(Request $request, Leave $leave): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return $this->fail(3003, '只有管理员可以审批');
        }

        if ($leave->approve_status !== ApproveStatus::PENDING) {
            return $this->fail(3002, '该申请已审批，请勿重复操作');
        }

        $balance = null;
        DB::transaction(function () use ($leave, $request, &$balance) {
            $leave->update([
                'approve_status' => ApproveStatus::APPROVED,
                'approver_id' => $request->user()->id,
                'approved_at' => now(),
            ]);

            // 联动：请假区间内的排班状态置「请假」
            Schedule::where('user_id', $leave->user_id)
                ->whereBetween('date', [$leave->start_date, $leave->end_date])
                ->update(['status' => ScheduleStatus::LEAVE, 'leave_id' => $leave->id]);

            // 年假扣减
            if ($leave->type === LeaveType::ANNUAL) {
                $balance = LeaveBalance::where('user_id', $leave->user_id)->first();
                if ($balance) {
                    $balance->remain_days = max(0, (float) $balance->remain_days - $leave->days());
                    $balance->save();
                }
            }
        });

        $msg = '已同意，排班状态已更新为请假';
        if ($leave->type === LeaveType::ANNUAL && $balance) {
            $msg .= sprintf('，年假剩余 %.1f 天', (float) $balance->remain_days);
        }

        return $this->ok($this->toRow($leave->refresh()), $msg);
    }

    /** POST /api/leaves/{leave}/reject 拒绝 */
    public function reject(Request $request, Leave $leave): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return $this->fail(3003, '只有管理员可以审批');
        }

        if ($leave->approve_status !== ApproveStatus::PENDING) {
            return $this->fail(3002, '该申请已审批，请勿重复操作');
        }

        $leave->update([
            'approve_status' => ApproveStatus::REJECTED,
            'approver_id' => $request->user()->id,
            'approved_at' => now(),
        ]);

        return $this->ok($this->toRow($leave->refresh()), '已拒绝');
    }

    /** POST /api/leaves/upload 请假图片上传（jpg/png，≤5MB） */
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png', 'max:5120'],
        ], [
            'file.required' => '请选择要上传的图片',
            'file.mimes' => '仅支持 jpg / png 格式',
            'file.max' => '单张图片不能超过 5MB',
        ]);

        $path = $request->file('file')->store('leave', 'local');
        $name = basename($path);

        return $this->ok(['name' => $name, 'url' => "/api/leaves/image?name={$name}"], '上传成功');
    }

    /**
     * GET /api/leaves/image?name=xxx.png 读取图片
     * 注意：路由刻意不带 .png 后缀——php -S 内置服务器对带扩展名路径不转发 Laravel
     */
    public function image(Request $request)
    {
        $name = (string) $request->input('name', '');
        if (! preg_match('/^[A-Za-z0-9_\-]{10,80}\.(jpg|jpeg|png)$/', $name)) {
            abort(404);
        }

        $disk = Storage::disk('local');
        if (! $disk->exists("leave/{$name}")) {
            abort(404);
        }

        return response()->file($disk->path("leave/{$name}"), ['Cache-Control' => 'private, max-age=86400']);
    }

    /** 同步附件（全量覆盖：以提交的文件名为准） */
    private function syncImages(Leave $leave, array $names, User $user): void
    {
        $names = array_values(array_filter(array_map('strval', $names)));

        Attachment::where('related_type', 'leave')
            ->where('related_id', $leave->id)
            ->whereNotIn('path', $names)
            ->get()
            ->each(function ($att) {
                Storage::disk('local')->delete($att->path);
                $att->delete();
            });

        $existing = Attachment::where('related_type', 'leave')
            ->where('related_id', $leave->id)
            ->pluck('path')
            ->all();

        foreach ($names as $name) {
            if (in_array($name, $existing, true)) {
                continue;
            }
            Attachment::create([
                'related_type' => 'leave',
                'related_id' => $leave->id,
                'path' => $name,
                'original_name' => $name,
                'uploader_id' => $user->id,
            ]);
        }
    }

    /** 撤销已通过的联动效果（排班恢复正常 + 年假回补） */
    private function revert(Leave $leave): void
    {
        if ($leave->approve_status !== ApproveStatus::APPROVED) {
            return;
        }

        Schedule::where('leave_id', $leave->id)
            ->update(['status' => ScheduleStatus::NORMAL, 'leave_id' => null]);

        if ($leave->type === LeaveType::ANNUAL) {
            $balance = LeaveBalance::where('user_id', $leave->user_id)->first();
            if ($balance) {
                $balance->remain_days = min(
                    (float) $balance->total_days,
                    (float) $balance->remain_days + $leave->days()
                );
                $balance->save();
            }
        }
    }

    /** 默认审批人：系统管理员（原型写死刘天骄，这里取管理员中第一人） */
    private function defaultApproverId(): ?int
    {
        return User::where('identity', 'admin')->orderBy('id')->value('id');
    }

    private function toRow(Leave $leave): array
    {
        $images = Attachment::where('related_type', 'leave')
            ->where('related_id', $leave->id)
            ->orderBy('id')
            ->pluck('path')
            ->all();

        return [
            'id' => $leave->id,
            'user_id' => $leave->user_id,
            'username' => $leave->user?->username,
            'applied_at' => $leave->applied_at?->format('Y-m-d H:i'),
            'type' => $leave->type->value,
            'type_label' => $leave->type->label(),
            'reason' => $leave->reason,
            'start_date' => $leave->start_date?->format('Y-m-d'),
            'end_date' => $leave->end_date?->format('Y-m-d'),
            'time_range' => $leave->start_date?->format('Y-m-d').' 至 '.$leave->end_date?->format('Y-m-d'),
            'days' => $leave->days(),
            'images' => array_map(fn ($n) => "/api/leaves/image?name={$n}", $images),
            'substitute_user_id' => $leave->substitute_user_id,
            'substitute' => $leave->substitute?->username,
            'approver_id' => $leave->approver_id,
            'approver' => $leave->approver?->username,
            'status' => $leave->approve_status->value,
            'status_label' => $leave->approve_status->label(),
            'status_tag' => $leave->approve_status->tagType(),
            'approved_at' => $leave->approved_at?->format('Y-m-d H:i'),
            'created_at' => $leave->created_at?->format('Y-m-d H:i'),
            'updated_at' => $leave->updated_at?->format('Y-m-d H:i'),
        ];
    }
}
