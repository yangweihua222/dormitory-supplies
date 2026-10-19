<?php

namespace App\Http\Controllers\Api;

use App\Enums\MaterialKind;
use App\Http\Controllers\Controller;
use App\Models\MaterialStock;
use App\Models\Room;
use App\Models\User;
use App\Support\ApiResponse;
use App\Support\ExportsExcel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * 入库 / 出库管理（需求 §8 子模块②③）
 * 一个控制器按 direction 服务两个菜单：入库（menu:inbound）/ 出库（menu:outbound）。
 * 字段对齐四份台账 Excel：固定资产含资产编号，耗材类不含。
 */
class MaterialStockController extends Controller
{
    use ApiResponse;
    use ExportsExcel;

    public function index(Request $request, string $direction): JsonResponse
    {
        $this->assertDirection($direction);

        $query = MaterialStock::query()->where('direction', $direction);

        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }
        if ($manager = $request->input('manager')) {
            $query->where('manager', $manager);
        }
        if ($category = trim((string) $request->input('category', ''))) {
            $query->where('category', 'like', "%{$category}%");
        }
        if ($date = $request->input('date')) {
            $query->whereDate('stock_date', $date);
        }

        $paginator = $query->orderByDesc('stock_date')->orderByDesc('id')
            ->paginate((int) $request->input('pageSize', 10));

        $paginator->setCollection($paginator->getCollection()->map(fn ($s) => $this->toRow($s)));

        return $this->page($paginator);
    }

    public function options(): JsonResponse
    {
        return $this->ok([
            'types' => array_map(fn ($c) => ['value' => $c->value, 'label' => $c->stockLabel()], MaterialKind::cases()),
            'managers' => User::query()->orderBy('id')->pluck('username'),
            'locations' => Room::query()->orderBy('room_no')->pluck('room_no'),
        ]);
    }

    /** GET /api/material-stocks/{direction}/export 出入库流水导出（复用列表筛选） */
    public function export(Request $request, string $direction)
    {
        $this->assertDirection($direction);
        $query = MaterialStock::query()->where('direction', $direction);

        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }
        if ($manager = $request->input('manager')) {
            $query->where('manager', $manager);
        }
        if ($category = trim((string) $request->input('category', ''))) {
            $query->where('category', 'like', "%{$category}%");
        }
        if ($date = $request->input('date')) {
            $query->whereDate('stock_date', $date);
        }

        $rows = $query->orderByDesc('stock_date')->orderByDesc('id')->get()
            ->map(fn ($s) => [
                $s->stock_date?->format('Y-m-d'),
                $s->type->stockLabel(),
                $s->category,
                $s->qty,
                $s->code,
                $s->manager,
                $s->location,
                $s->remark,
            ])->all();

        $prefix = $direction === 'in' ? '入库记录导出' : '出库记录导出';

        return $this->xlsxDownload($prefix, ['日期', '类型', '类别', '数量', '资产编号', '经办人', '房间号/位置', '备注'], $rows);
    }

    public function store(Request $request, string $direction): JsonResponse
    {
        $this->assertDirection($direction);
        $data = $request->validate($this->rules(), $this->messages());
        if ($data['type'] === 'consumable') {
            $data['code'] = null; // 耗材类无资产编号（台账口径）
        }
        $data['direction'] = $direction;
        $data['created_by'] = $request->user()->id;

        $row = MaterialStock::create($data);

        return $this->ok($this->toRow($row->refresh()), '已添加');
    }

    /**
     * 注意参数顺序：路由 URI 为 /material-stocks/{direction 字面量}/{stock}，
     * direction 通过 ->defaults() 注入，路由参数实际顺序是 [stock, direction]，
     * 与之一致才能正确绑定（顺序写反会因类型不符抛 TypeError → 500）。
     */
    public function update(Request $request, MaterialStock $stock, string $direction): JsonResponse
    {
        $this->assertDirection($direction);
        abort_if($stock->direction !== $direction, 404);

        $data = $request->validate($this->rules(), $this->messages());
        if ($data['type'] === 'consumable') {
            $data['code'] = null;
        }
        $stock->fill($data)->save();

        return $this->ok($this->toRow($stock->refresh()), '已保存');
    }

    /** 同 update：参数顺序需与路由参数 [stock, direction] 保持一致 */
    public function destroy(MaterialStock $stock, string $direction): JsonResponse
    {
        $this->assertDirection($direction);
        abort_if($stock->direction !== $direction, 404);
        $stock->delete();

        return $this->ok(null, '已删除');
    }

    private function assertDirection(string $direction): void
    {
        abort_unless(in_array($direction, ['in', 'out'], true), 404);
    }

    private function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['fixed', 'consumable'])],
            'category' => ['required', 'string', 'max:100'],
            'qty' => ['required', 'integer', 'min:1'],
            'code' => ['nullable', 'string', 'max:50'],
            'stock_date' => ['required', 'date'],
            'manager' => ['required', 'string', 'max:50'],
            'location' => ['required', 'string', 'max:100'],
            'remark' => ['nullable', 'string', 'max:500'],
        ];
    }

    private function messages(): array
    {
        return [
            'type.required' => '请选择类型',
            'category.required' => '请输入名称类别',
            'qty.required' => '请填写数量',
            'stock_date.required' => '请选择日期',
            'manager.required' => '请选择管理人',
            'location.required' => '请选择地点',
        ];
    }

    private function toRow(MaterialStock $s): array
    {
        return [
            'id' => $s->id,
            'direction' => $s->direction,
            'type' => $s->type->value,
            'type_label' => $s->type->stockLabel(),
            'category' => $s->category,
            'qty' => $s->qty,
            'code' => $s->code,
            'stock_date' => $s->stock_date?->format('Y-m-d'),
            'manager' => $s->manager,
            'location' => $s->location,
            'remark' => $s->remark,
            'created_at' => $s->created_at?->format('Y-m-d H:i'),
            'updated_at' => $s->updated_at?->format('Y-m-d H:i'),
        ];
    }
}
