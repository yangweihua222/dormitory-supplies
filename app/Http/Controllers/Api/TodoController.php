<?php

namespace App\Http\Controllers\Api;

use App\Enums\TodoStatus;
use App\Http\Controllers\Controller;
use App\Models\Todo;
use App\Services\MaterialAlertService;
use App\Support\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TodoController extends Controller
{
    use ApiResponse;

    /**
     * 待办列表
     * GET /api/todos
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // 物资报废年限懒检查（需求 §六：每年 5 月 1 日自动；仅管理员/徐长东拉待办时触发，幂等）
        if ($user->isAdmin() || $user->canSeeMaterialAlerts()) {
            MaterialAlertService::runAnnualScrapCheck();
        }

        $page = (int) $request->input('page', 1);
        // 条数可由前端按卡片可用高度自适应传入（1~20）
        $size = (int) $request->input('size', Todo::PAGE_SIZE);
        $size = max(1, min(20, $size));

        return $this->ok(Todo::feed($user, $page, $size));
    }

    /**
     * 完成单个待办
     * POST /api/todos/{id}/complete
     */
    public function complete(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $todo = Todo::with('assignees')->findOrFail($id);

        if ($todo->status === TodoStatus::COMPLETED) {
            return $this->fail(4007, '该待办已完成');
        }

        // 权限口径与列表可见范围一致（需求 §1.1：管理员看全部，员工看本人）
        if (! Todo::canOperate($user, $todo)) {
            return $this->fail(1002, '无权限');
        }

        $todo->status = TodoStatus::COMPLETED;
        $todo->completed_at = Carbon::now();
        $todo->save();

        // 记录该指派人的完成时间
        $todo->assignees()->where('user_id', $user->id)->update(['completed_at' => Carbon::now()]);

        return $this->ok(['id' => $todo->id], '完成成功');
    }

    /**
     * 一键完成当前页全部待办
     * POST /api/todos/complete-all
     */
    public function completeAll(Request $request): JsonResponse
    {
        $user = $request->user();
        $ids = $request->input('ids', []);

        $query = Todo::visibleTo($user)
            ->whereIn('id', $ids)
            ->where('status', TodoStatus::PENDING);

        $now = Carbon::now();
        // 逐条 save 而非批量 update：确保模型事件触发（维修待办完成 → 报修置「已完成」）
        $affected = 0;
        foreach ($query->get() as $todo) {
            $todo->status = TodoStatus::COMPLETED;
            $todo->completed_at = $now;
            $todo->save();
            $todo->assignees()->where('user_id', $user->id)->update(['completed_at' => $now]);
            $affected++;
        }

        return $this->ok(['affected' => $affected], '一键完成成功');
    }
}
