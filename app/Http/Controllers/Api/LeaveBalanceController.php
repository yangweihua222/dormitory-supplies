<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LeaveBalance;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 假勤管理（需求 §4.2）——年假录入
 */
class LeaveBalanceController extends Controller
{
    use ApiResponse;

    /** GET /api/leave-balances 列表（筛选：人员关键字 / 剩余天数） */
    public function index(Request $request): JsonResponse
    {
        $query = LeaveBalance::with('user');

        if ($kw = trim((string) $request->input('keyword', ''))) {
            $query->whereHas('user', fn ($q) => $q->where('username', 'like', "%{$kw}%"));
        }

        $left = $request->input('remain'); // gt0 | eq0
        if ($left === 'gt0') {
            $query->where('remain_days', '>', 0);
        } elseif ($left === 'eq0') {
            $query->where('remain_days', '<=', 0);
        }

        $paginator = $query->orderBy('id')
            ->paginate((int) $request->input('pageSize', 10));

        $paginator->setCollection($paginator->getCollection()->map(fn ($b) => [
            'id' => $b->id,
            'user_id' => $b->user_id,
            'username' => $b->user?->username,
            'total_days' => (float) $b->total_days,
            'remain_days' => (float) $b->remain_days,
            'created_at' => $b->created_at?->format('Y-m-d H:i'),
            'updated_at' => $b->updated_at?->format('Y-m-d H:i'),
        ]));

        return $this->page($paginator);
    }

    /** POST /api/leave-balances 添加假期 */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id', 'unique:leave_balances,user_id'],
            'total_days' => ['required', 'numeric', 'min:0', 'max:999'],
            'remain_days' => ['required', 'numeric', 'min:0', 'max:999'],
        ], [
            'user_id.required' => '请选择人员',
            'user_id.unique' => '该人员已有年假记录，请直接编辑',
        ]);

        $data['remain_days'] = min((float) $data['remain_days'], (float) $data['total_days']);

        $balance = LeaveBalance::create($data);
        $balance->load('user');

        return $this->ok(['id' => $balance->id], '已添加');
    }

    /** PUT /api/leave-balances/{balance} 编辑 */
    public function update(Request $request, LeaveBalance $balance): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id', 'unique:leave_balances,user_id,'.$balance->id],
            'total_days' => ['required', 'numeric', 'min:0', 'max:999'],
            'remain_days' => ['required', 'numeric', 'min:0', 'max:999'],
        ], [
            'user_id.unique' => '该人员已有年假记录',
        ]);

        $data['remain_days'] = min((float) $data['remain_days'], (float) $data['total_days']);

        $balance->fill($data)->save();

        return $this->ok(['id' => $balance->id], '已保存');
    }

    /** DELETE /api/leave-balances/{balance} 删除 */
    public function destroy(LeaveBalance $balance): JsonResponse
    {
        $balance->delete();

        return $this->ok(null, '已删除');
    }

    /** GET /api/leave-balances/options 可选人员（尚未录入年假的人 + 当前编辑对象） */
    public function options(Request $request): JsonResponse
    {
        $except = array_filter([(int) $request->input('except_id', 0)]);
        $taken = LeaveBalance::pluck('user_id')->all();

        $users = User::whereNotIn('id', array_diff($taken, $except))
            ->orderBy('employee_no')
            ->get(['id', 'username', 'employee_no']);

        return $this->ok($users);
    }
}
