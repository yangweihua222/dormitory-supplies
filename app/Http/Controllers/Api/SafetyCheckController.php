<?php

namespace App\Http\Controllers\Api;

use App\Enums\CheckResult;
use App\Enums\EmergencyCategory;
use App\Enums\SafetyCategory;
use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Models\SafetyCheck;
use App\Models\User;
use App\Support\ApiResponse;
use App\Support\ExportsExcel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * 安全检查列表（需求 §7.1，UI 对齐原型）
 * 一张表承载 6 个子类：检查类（宿舍消防安全检查，
 * 逐房间用电安全表单：房间号 + 检查人员 + 外来排查人员 + 6 项用电检查 + 检查结果 + 整改状态 + 备注）
 * 与事件类（公共秩序 / 财产安全 / 人身安全 / 环境卫生安全 / 突发类安全事件）。
 * 原型无导入 / 模板下载；P3 报表导出增值需求，提供导出接口。
 */
class SafetyCheckController extends Controller
{
    use ApiResponse;
    use ExportsExcel;

    /** 6 项用电安全检查（key / 标签与原型表单一致） */
    public const FIRE_ITEMS = [
        ['key' => 'private_wiring', 'label' => '是否私拉乱接电线', 'en' => 'Private wiring'],
        ['key' => 'strip_overload', 'label' => '插线板是否过载', 'en' => 'Power strip overload'],
        ['key' => 'high_wattage', 'label' => '是否使用大于或者等于1000瓦电器设备', 'en' => 'High-wattage appliance (≥1000W)'],
        ['key' => 'power_off', 'label' => '是否人走断电', 'en' => 'Power-off on leaving'],
        ['key' => 'trash_at_door', 'label' => '门口是否存放垃圾、废旧物品', 'en' => 'Block fire exit with trash'],
        ['key' => 'smoke_blocked', 'label' => '遮挡烟感报警器', 'en' => 'Block smoke detector'],
    ];

    /** GET /api/safety-checks 列表（筛选：类别多选 / 楼层 / 检查结果 / 整改状态 / 日期） */
    public function index(Request $request): JsonResponse
    {
        $query = SafetyCheck::query();

        $categories = array_values(array_filter((array) $request->input('categories', [])));
        if ($categories) {
            $query->whereIn('category', $categories);
        }

        // 检查结果 / 整改状态 / 楼层仅对检查类记录有意义（原型口径；检查类仅宿舍消防安全检查）
        if ($result = $request->input('result')) {
            $query->where('category', SafetyCategory::DORM_FIRE->value)
                ->where('result', $result);
        }
        if ($rectify = $request->input('rectify')) {
            $query->where('category', SafetyCategory::DORM_FIRE->value)
                ->where('result', 'abnormal')
                ->where('rectify', $rectify);
        }
        if ($floor = $request->input('floor')) {
            $roomIds = Room::all()
                ->filter(fn ($r) => self::floorOf($r->room_no) === (int) $floor)
                ->pluck('id');
            $query->whereIn('room_id', $roomIds);
        }
        if ($date = $request->input('date')) {
            $query->whereDate('check_date', $date);
        }

        $paginator = $query->with('room')
            ->orderByDesc('check_date')->orderByDesc('id')
            ->paginate((int) $request->input('pageSize', 10));

        $paginator->setCollection($paginator->getCollection()->map(fn ($c) => $this->toRow($c)));

        return $this->page($paginator);
    }

    /** GET /api/safety-checks/options 字典 + 检查人员 / 房间下拉 + 楼层 */
    public function options(): JsonResponse
    {
        $rooms = Room::orderBy('room_no')->get(['id', 'room_no']);
        $floors = $rooms->map(fn ($r) => self::floorOf($r->room_no))
            ->filter()->unique()->sort()->values();

        return $this->ok([
            'categories' => SafetyCategory::options(),
            'results' => CheckResult::options(),
            'event_categories' => EmergencyCategory::options(),
            'fire_items' => self::FIRE_ITEMS,
            'rooms' => $rooms->map(fn ($r) => [
                'id' => $r->id,
                'room_no' => $r->room_no,
                'floor' => self::floorOf($r->room_no),
            ])->all(),
            'floors' => $floors->all(),
            'users' => User::orderBy('username')
                ->get(['id', 'username'])
                ->map(fn ($u) => ['id' => $u->id, 'name' => $u->username])
                ->all(),
        ]);
    }

    /** GET /api/safety-checks/export 导出（复用列表筛选；检查类含 6 项用电检查，事件类含事件字段） */
    public function export(Request $request)
    {
        $query = SafetyCheck::query();

        $categories = array_values(array_filter((array) $request->input('categories', [])));
        if ($categories) {
            $query->whereIn('category', $categories);
        }
        if ($result = $request->input('result')) {
            $query->where('category', SafetyCategory::DORM_FIRE->value)
                ->where('result', $result);
        }
        if ($rectify = $request->input('rectify')) {
            $query->where('category', SafetyCategory::DORM_FIRE->value)
                ->where('result', 'abnormal')
                ->where('rectify', $rectify);
        }
        if ($floor = $request->input('floor')) {
            $roomIds = Room::all()
                ->filter(fn ($r) => self::floorOf($r->room_no) === (int) $floor)
                ->pluck('id');
            $query->whereIn('room_id', $roomIds);
        }
        if ($date = $request->input('date')) {
            $query->whereDate('check_date', $date);
        }

        $rows = $query->with('room')->orderByDesc('check_date')->orderByDesc('id')->get()
            ->map(function ($c) {
                $isEvent = $c->category === SafetyCategory::EMERGENCY;

                return [
                    $c->category->label(),
                    $c->check_date?->format('Y-m-d'),
                    $isEvent ? $c->place : $c->room?->room_no,
                    $isEvent ? $c->event_time?->format('Y-m-d H:i') : $c->result?->label(),
                    $isEvent ? $c->event_category?->label() : ($c->result?->value === 'abnormal' ? $c->rectifyLabel() : '—'),
                    $isEvent ? null : ($c->category->isCheck() ? ($c->external_inspector ?: implode('、', $c->inspectorNames())) : implode('、', $c->inspectorNames())),
                    $isEvent ? $c->person : null,
                    $isEvent ? $c->process : null,
                    $c->remark,
                ];
            })->all();

        $headers = ['类别', '日期', '房间号/地点', '检查结果/事件时间', '整改状态/事件类别', '检查人/外部人员', '当事人', '处理过程', '备注'];

        return $this->xlsxDownload('安全检查导出', $headers, $rows);
    }

    /** POST /api/safety-checks 新增 */
    public function store(Request $request): JsonResponse
    {
        $category = SafetyCategory::tryFrom((string) $request->input('category', ''));
        if (! $category) {
            return $this->fail(1001, '请选择检查类别');
        }

        $data = $request->validate($this->rules($category), $this->messages($category));
        $data = $this->normalize($data, $category);
        $data['created_by'] = $request->user()->id;

        $check = SafetyCheck::create($data);

        return $this->ok($this->toRow($check->refresh()), '已添加');
    }

    /** PUT /api/safety-checks/{safetyCheck} 编辑 */
    public function update(Request $request, SafetyCheck $safetyCheck): JsonResponse
    {
        $category = SafetyCategory::tryFrom((string) $request->input('category', '')) ?: $safetyCheck->category;

        $data = $request->validate($this->rules($category), $this->messages($category));
        $data = $this->normalize($data, $category);

        $safetyCheck->fill($data)->save();

        return $this->ok($this->toRow($safetyCheck->refresh()), '已保存');
    }

    /** DELETE /api/safety-checks/{safetyCheck} 删除 */
    public function destroy(SafetyCheck $safetyCheck): JsonResponse
    {
        $safetyCheck->delete();

        return $this->ok(null, '已删除');
    }

    /** POST /api/safety-checks/upload 现场图片上传 */
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
        ], [
            'file.required' => '请选择要上传的图片',
            'file.image' => '只能上传图片文件',
            'file.mimes' => '仅支持 jpg / png 格式',
            'file.max' => '单张图片不能超过 5MB',
        ]);

        $path = $request->file('file')->store('safety', 'local');
        $name = basename($path);

        return $this->ok(['name' => $name, 'url' => "/api/safety-checks/image?name={$name}"], '上传成功');
    }

    /** GET /api/safety-checks/image?name=xxx.png 读取图片（不带后缀，兼容 php -S） */
    public function image(Request $request)
    {
        $name = (string) $request->input('name', '');
        if (! preg_match('/^[A-Za-z0-9_\-]{10,80}\.(jpg|jpeg|png)$/', $name)) {
            abort(404);
        }

        $disk = Storage::disk('local');
        if (! $disk->exists("safety/{$name}")) {
            abort(404);
        }

        return response()->file($disk->path("safety/{$name}"), ['Cache-Control' => 'private, max-age=86400']);
    }

    /** 房间号 → 楼层（原型口径：如 A栋301室 → 3 层） */
    public static function floorOf(string $roomNo): ?int
    {
        if (! preg_match('/(\d+)室/', $roomNo, $m)) {
            return null;
        }

        return intdiv((int) $m[1], 100);
    }

    private function rules(SafetyCategory $category): array
    {
        $base = [
            'category' => ['required', 'in:'.implode(',', array_column(SafetyCategory::cases(), 'value'))],
            'check_date' => ['required', 'date'],
        ];

        if ($category->isCheck()) {
            return $base + [
                'room_id' => ['required', 'exists:rooms,id'],
                'inspector_ids' => ['required', 'array', 'min:1'],
                'inspector_ids.*' => ['integer', 'exists:users,id'],
                'external_inspector' => ['nullable', 'string', 'max:200'],
                'result' => ['required', 'in:normal,abnormal'],
                'rectify' => ['nullable', 'in:done,pending'],
                'remark' => ['nullable', 'string', 'max:500'],
            ];
        }

        $event = [
            'event_desc' => ['required', 'string'],
            'event_time' => ['required', 'date'],
            'place' => ['nullable', 'string', 'max:200'],
            'person' => ['nullable', 'string', 'max:200'],
            'process' => ['nullable', 'string'],
            'images' => ['nullable', 'array'],
            'images.*' => ['string', 'max:120'],
        ];

        if ($category === SafetyCategory::EMERGENCY) {
            $event['event_category'] = ['required', 'in:fire,health,mental,disaster,mass'];
        }

        return $base + $event;
    }

    private function messages(SafetyCategory $category): array
    {
        return [
            'check_date.required' => '请选择检查日期',
            'room_id.required' => '请选择房间号',
            'inspector_ids.required' => '请选择检查人员',
            'inspector_ids.min' => '请选择检查人员',
            'result.required' => '请选择检查结果',
            'rectify.in' => '整改状态不正确',
            'event_desc.required' => '请填写事件描述',
            'event_time.required' => '请选择事件时间',
            'event_category.required' => '请选择事件类别',
        ];
    }

    /** 按类别清洗：检查类清空事件字段，事件类清空检查字段 */
    private function normalize(array $data, SafetyCategory $category): array
    {
        if ($category->isCheck()) {
            // 检查结果 = 正常时，整改状态无意义，置空（原型：正常时整改状态禁用）
            if (($data['result'] ?? '') === 'normal') {
                $data['rectify'] = null;
            }
            $nulls = [
                'event_desc', 'event_time', 'place', 'person', 'process', 'images', 'event_category',
                'scope_range', 'location', 'content', 'summary',
            ];
        } else {
            $nulls = [
                'room_id', 'external_inspector', 'rectify',
                'private_wiring', 'strip_overload', 'high_wattage', 'power_off', 'trash_at_door', 'smoke_blocked',
                'scope_range', 'location', 'content', 'result', 'summary', 'inspector_ids',
            ];
            if ($category !== SafetyCategory::EMERGENCY) {
                $nulls[] = 'event_category';
            }
        }

        foreach ($nulls as $field) {
            $data[$field] = null;
        }

        return $data;
    }

    private function toRow(SafetyCheck $c): array
    {
        $items = [];
        foreach (self::FIRE_ITEMS as $it) {
            $items[$it['key']] = [
                'label' => $it['label'],
                'value' => $c->{$it['key']},
                'text' => $c->{$it['key']} === null ? null : ($c->{$it['key']} ? '是' : '否'),
            ];
        }

        return [
            'id' => $c->id,
            'category' => $c->category->value,
            'category_label' => $c->category->label(),
            'kind' => $c->category->kind(),
            'check_date' => $c->check_date?->format('Y-m-d'),
            'room_id' => $c->room_id,
            'room_no' => $c->room?->room_no,
            'floor' => $c->room ? self::floorOf($c->room->room_no) : null,
            'fire_items' => $items,
            'result' => $c->result?->value,
            'result_label' => $c->result?->label(),
            'result_type' => $c->result?->tagType(),
            'rectify' => $c->rectify,
            'rectify_label' => $c->rectifyLabel(),
            'external_inspector' => $c->external_inspector,
            'remark' => $c->remark,
            'inspector_ids' => $c->inspector_ids ?? [],
            'inspectors' => $c->inspectorNames(),
            'inspectors_text' => implode('、', $c->inspectorNames()),
            'event_desc' => $c->event_desc,
            'event_time' => $c->event_time?->format('Y-m-d H:i'),
            'place' => $c->place,
            'person' => $c->person,
            'process' => $c->process,
            'images' => array_map(fn ($n) => "/api/safety-checks/image?name={$n}", $c->images ?? []),
            'event_category' => $c->event_category?->value,
            'event_category_label' => $c->event_category?->label(),
            'creator' => $c->creator?->username,
            'created_at' => $c->created_at?->format('Y-m-d H:i'),
            'updated_at' => $c->updated_at?->format('Y-m-d H:i'),
        ];
    }
}
