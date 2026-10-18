<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ShiftStoreRequest;
use App\Http\Requests\ShiftUpdateRequest;
use App\Models\Shift;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShiftController extends Controller
{
    use ApiResponse;

    /** GET /api/shifts 班次列表 */
    public function index(Request $request): JsonResponse
    {
        $query = Shift::query();

        if ($kw = trim((string) $request->input('keyword', ''))) {
            $query->where('name', 'like', "%{$kw}%");
        }

        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }

        $paginator = $query->orderBy('created_at', 'desc')
            ->paginate((int) $request->input('pageSize', 10));

        $paginator->setCollection($paginator->getCollection()->map(fn ($s) => $this->toRow($s)));

        return $this->page($paginator);
    }

    /**
     * GET /api/shifts/options 班次字典（下拉用）
     * 跨模块公共字典（排班管理要选班次），只校验登录，不占用「班次管理」菜单权限
     */
    public function options(): JsonResponse
    {
        $shifts = Shift::orderBy('id')
            ->get()
            ->map(fn ($s) => $this->toRow($s));

        return $this->ok($shifts);
    }

    /** POST /api/shifts 新增班次 */
    public function store(ShiftStoreRequest $request): JsonResponse
    {
        $shift = Shift::create($request->validated());

        return $this->ok($this->toRow($shift), '已添加');
    }

    /** PUT /api/shifts/{shift} 编辑班次 */
    public function update(ShiftUpdateRequest $request, Shift $shift): JsonResponse
    {
        $shift->fill($request->validated());
        $shift->save();

        return $this->ok($this->toRow($shift), '已保存');
    }

    /** DELETE /api/shifts/{shift} 删除（软删除） */
    public function destroy(Shift $shift): JsonResponse
    {
        $shift->delete();

        return $this->ok(null, '已删除');
    }

    private function toRow(Shift $shift): array
    {
        return [
            'id' => $shift->id,
            'name' => $shift->name,
            'check_in_time' => $shift->check_in_time?->format('H:i:s'),
            'check_out_time' => $shift->check_out_time?->format('H:i:s'),
            'type' => $shift->type->value,
            'type_label' => $shift->type->label(),
            'created_at' => $shift->created_at?->format('Y-m-d H:i'),
            'updated_at' => $shift->updated_at?->format('Y-m-d H:i'),
        ];
    }
}
