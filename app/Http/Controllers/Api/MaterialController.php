<?php

namespace App\Http\Controllers\Api;

use App\Enums\ConsumableSub;
use App\Enums\MaterialKind;
use App\Enums\MaterialUse;
use App\Http\Controllers\Controller;
use App\Models\Material;
use App\Models\Room;
use App\Services\MaterialAlertService;
use App\Support\ApiResponse;
use App\Support\ExportsExcel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * 物资列表 / 台账（需求 §8 子模块①）
 * 搜索维度：种类 / 房间号(放置地点) / 用途 / 是否已报废（原型）；年限搜索按文档另支持预计/实际报废年限。
 * ★真工程：保存时做「房间配置数量校验」——不一致先返回待确认（code=1001），前端弹框确认后带
 * confirm_mismatch=1 重提，落库并生成「数量不符」待办（管理员 / 徐长东）。
 */
class MaterialController extends Controller
{
    use ApiResponse;
    use ExportsExcel;

    /** 前端确认弹框后重提的业务码 */
    public const CODE_MISMATCH_CONFIRM = 1001;

    public function index(Request $request): JsonResponse
    {
        $query = Material::query();

        if ($kind = $request->input('kind')) {
            $query->where('kind', $kind);
        }
        if ($sub = $request->input('consumable_sub')) {
            $query->where('consumable_sub', $sub);
        }
        if ($location = $request->input('location')) {
            $query->where('location', $location);
        }
        if ($use = $request->input('use_type')) {
            $query->where('use_type', $use);
        }
        if ($request->filled('scrapped')) {
            $query->where('scrapped', (bool) $request->input('scrapped'));
        }
        if ($year = $request->input('scrap_year')) {
            $query->where('scrap_year', $year);
        }
        if ($year = $request->input('actual_scrap_year')) {
            $query->where('actual_scrap_year', $year);
        }
        if ($name = trim((string) $request->input('name', ''))) {
            $query->where('name', 'like', "%{$name}%");
        }

        $paginator = $query->orderByDesc('id')
            ->paginate((int) $request->input('pageSize', 10));

        $paginator->setCollection($paginator->getCollection()->map(fn ($m) => $this->toRow($m)));

        return $this->page($paginator);
    }

    public function options(): JsonResponse
    {
        return $this->ok([
            'kinds' => MaterialKind::options(),
            'consumable_subs' => ConsumableSub::options(),
            'uses' => MaterialUse::options(),
            'furniture_kinds' => RoomController::FURNITURE_KINDS,
            // 放置地点 = 房源管理的房间号
            'locations' => Room::query()->orderBy('room_no')->pluck('room_no'),
        ]);
    }

    /** GET /api/materials/export 物资列表导出（复用列表筛选） */
    public function export(Request $request)
    {
        $query = Material::query();
        if ($kind = $request->input('kind')) {
            $query->where('kind', $kind);
        }
        if ($sub = $request->input('consumable_sub')) {
            $query->where('consumable_sub', $sub);
        }
        if ($location = $request->input('location')) {
            $query->where('location', $location);
        }
        if ($use = $request->input('use_type')) {
            $query->where('use_type', $use);
        }
        if ($request->filled('scrapped')) {
            $query->where('scrapped', (bool) $request->input('scrapped'));
        }
        if ($year = $request->input('scrap_year')) {
            $query->where('scrap_year', $year);
        }
        if ($year = $request->input('actual_scrap_year')) {
            $query->where('actual_scrap_year', $year);
        }
        if ($name = trim((string) $request->input('name', ''))) {
            $query->where('name', 'like', "%{$name}%");
        }

        $rows = $query->orderByDesc('id')->get()
            ->map(fn ($m) => [
                $m->kindLabel(),
                $m->kind->value === 'consumable' ? ($m->consumable_sub?->label() ?? '') : '',
                $m->name,
                $m->code,
                $m->furniture_kind,
                $m->qty,
                $m->location,
                $m->use_type->label(),
                $m->purchase_year,
                $m->scrap_year,
                $m->scrapped ? '已报废' : '未报废',
                $m->actual_scrap_year,
                $m->stock,
                $m->stock_in_at?->format('Y-m-d'),
                $m->stock_in_by,
                $m->stock_out_at?->format('Y-m-d'),
                $m->stock_out_by,
            ])->all();

        return $this->xlsxDownload('物资列表导出', [
            '种类', '耗材子类', '名称', '资产编号', '家具种类', '数量', '房间号', '用途',
            '采购年限', '预计报废年限', '是否已报废', '实际报废年限', '库存数量',
            '入库时间', '入库人', '出库时间', '出库人',
        ], $rows);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules(), $this->messages());

        // 房间配置数量校验：不一致且未确认 → 返回待确认业务码，由前端弹框
        $check = MaterialAlertService::checkRoomConfig(
            $data['location'], $data['furniture_kind'] ?? null, (int) $data['qty']
        );
        if ($check['matched'] && $check['configured'] !== (int) $data['qty']
            && ! $request->boolean('confirm_mismatch')) {
            return $this->fail(self::CODE_MISMATCH_CONFIRM, '数量与房间配置不一致', 200, [
                'configured' => $check['configured'],
                'qty' => (int) $data['qty'],
                'location' => $data['location'],
                'furniture_kind' => $data['furniture_kind'],
            ]);
        }

        if (($data['kind'] ?? null) === 'fixed') {
            $data['consumable_sub'] = null;
        }
        $data['created_by'] = $request->user()->id;

        $row = Material::create($data);

        if ($check['matched'] && $check['configured'] !== (int) $data['qty']) {
            MaterialAlertService::createQtyMismatchTodo($row, $check['configured']);
        }

        return $this->ok($this->toRow($row->refresh()), '已添加');
    }

    public function update(Request $request, Material $material): JsonResponse
    {
        $data = $request->validate($this->rules(), $this->messages());

        $check = MaterialAlertService::checkRoomConfig(
            $data['location'], $data['furniture_kind'] ?? null, (int) $data['qty']
        );
        if ($check['matched'] && $check['configured'] !== (int) $data['qty']
            && ! $request->boolean('confirm_mismatch')) {
            return $this->fail(self::CODE_MISMATCH_CONFIRM, '数量与房间配置不一致', 200, [
                'configured' => $check['configured'],
                'qty' => (int) $data['qty'],
                'location' => $data['location'],
                'furniture_kind' => $data['furniture_kind'],
            ]);
        }

        if (($data['kind'] ?? null) === 'fixed') {
            $data['consumable_sub'] = null;
        }
        $material->fill($data)->save();

        if ($check['matched'] && $check['configured'] !== (int) $data['qty']) {
            MaterialAlertService::createQtyMismatchTodo($material->refresh(), $check['configured']);
        }

        return $this->ok($this->toRow($material->refresh()), '已保存');
    }

    public function destroy(Material $material): JsonResponse
    {
        $material->delete();

        return $this->ok(null, '已删除');
    }

    private function rules(): array
    {
        return [
            'kind' => ['required', Rule::in(['fixed', 'consumable'])],
            'consumable_sub' => ['nullable', 'required_if:kind,consumable', Rule::in(array_column(ConsumableSub::cases(), 'value'))],
            'name' => ['required', 'string', 'max:100'],
            'purchase_year' => ['required', 'integer', 'min:1990', 'max:2100'],
            'scrap_year' => ['nullable', 'integer', 'min:1990', 'max:2100'],
            'scrapped' => ['required', 'boolean'],
            'actual_scrap_year' => ['nullable', 'integer', 'min:1990', 'max:2100'],
            'location' => ['required', 'string', 'max:100'],
            'use_type' => ['required', Rule::in(['office', 'life'])],
            'furniture_kind' => ['nullable', 'string', 'max:50'],
            'code' => ['nullable', 'string', 'max:50'],
            'qty' => ['required', 'integer', 'min:0'],
            'stock_in_at' => ['nullable', 'date'],
            'stock_in_by' => ['nullable', 'string', 'max:50'],
            'stock_out_at' => ['nullable', 'date'],
            'stock_out_by' => ['nullable', 'string', 'max:50'],
            'stock' => ['nullable', 'integer', 'min:0'],
            'confirm_mismatch' => ['nullable', 'boolean'],
        ];
    }

    private function messages(): array
    {
        return [
            'kind.required' => '请选择种类',
            'consumable_sub.required_if' => '耗材物资请选择耗材子类',
            'name.required' => '请输入物资名称',
            'purchase_year.required' => '请填写采购年限',
            'location.required' => '请选择放置地点',
            'use_type.required' => '请选择用途',
            'qty.required' => '请填写数量',
        ];
    }

    private function toRow(Material $m): array
    {
        return [
            'id' => $m->id,
            'kind' => $m->kind->value,
            'kind_label' => $m->kindLabel(),
            'consumable_sub' => $m->consumable_sub?->value,
            'consumable_sub_label' => $m->consumable_sub?->label(),
            'name' => $m->name,
            'purchase_year' => $m->purchase_year,
            'scrap_year' => $m->scrap_year,
            'scrapped' => $m->scrapped,
            'actual_scrap_year' => $m->actual_scrap_year,
            'location' => $m->location,
            'use_type' => $m->use_type->value,
            'use_type_label' => $m->use_type->label(),
            'furniture_kind' => $m->furniture_kind,
            'code' => $m->code,
            'qty' => $m->qty,
            'stock_in_at' => $m->stock_in_at?->format('Y-m-d'),
            'stock_in_by' => $m->stock_in_by,
            'stock_out_at' => $m->stock_out_at?->format('Y-m-d'),
            'stock_out_by' => $m->stock_out_by,
            'stock' => $m->stock,
            'created_at' => $m->created_at?->format('Y-m-d H:i'),
            'updated_at' => $m->updated_at?->format('Y-m-d H:i'),
        ];
    }
}
