<?php

namespace App\Http\Controllers\Api;

use App\Enums\RoomType;
use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Models\RoomFurniture;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * 房源管理（需求 §5.1）
 * 房间物资 = room_furnitures（家具种类 × 数量，可多条），是 §8 物资校验的数据源
 */
class RoomController extends Controller
{
    use ApiResponse;

    /** 家具种类（与原型一致） */
    public const FURNITURE_KINDS = ['床类', '桌椅类', '柜类', '其他'];

    /** GET /api/rooms 列表（筛选：房间编码 / 房间号 / 类型） */
    public function index(Request $request): JsonResponse
    {
        $query = Room::with('furnitures');

        if ($kw = trim((string) $request->input('room_code', ''))) {
            $query->where('room_code', 'like', "%{$kw}%");
        }
        if ($kw = trim((string) $request->input('room_no', ''))) {
            $query->where('room_no', 'like', "%{$kw}%");
        }
        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }

        $paginator = $query->orderBy('room_code')
            ->paginate((int) $request->input('pageSize', 10));

        $paginator->setCollection($paginator->getCollection()->map(fn ($r) => $this->toRow($r)));

        return $this->page($paginator);
    }

    /** GET /api/rooms/all 房间下拉（住宿 / 房卡表单用） */
    public function all(): JsonResponse
    {
        return $this->ok(
            Room::orderBy('room_code')->get()->map(fn ($r) => [
                'id' => $r->id,
                'room_code' => $r->room_code,
                'room_no' => $r->room_no,
                'beds' => $r->beds,
            ])
        );
    }

    /** GET /api/rooms/options 类型与家具种类选项 */
    public function options(): JsonResponse
    {
        return $this->ok([
            'types' => RoomType::options(),
            'furniture_kinds' => self::FURNITURE_KINDS,
        ]);
    }

    /** POST /api/rooms 添加房源 */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'room_code' => ['required', 'string', 'max:50', 'unique:rooms,room_code'],
            'room_no' => ['required', 'string', 'max:50'],
            'type' => ['required', 'in:dorm,kitchen,warehouse,drying,office,public'],
            'beds' => ['required', 'integer', 'min:0'],
            'remark' => ['nullable', 'string', 'max:500'],
            'furnitures' => ['nullable', 'array'],
            'furnitures.*.kind' => ['required_with:furnitures', 'string', 'max:50'],
            'furnitures.*.count' => ['required_with:furnitures', 'integer', 'min:0'],
        ], [
            'room_code.required' => '请填写房间编码',
            'room_code.unique' => '该房间编码已存在',
            'room_no.required' => '请填写房间号',
            'type.required' => '请选择类型',
            'beds.required' => '请填写床位数',
        ]);

        $room = DB::transaction(function () use ($data) {
            $room = Room::create([
                'room_code' => $data['room_code'],
                'room_no' => $data['room_no'],
                'type' => $data['type'],
                'beds' => $data['beds'],
                'remark' => $data['remark'] ?? null,
            ]);

            $this->syncFurnitures($room, $data['furnitures'] ?? []);

            return $room;
        });

        return $this->ok($this->toRow($room), '已添加');
    }

    /** PUT /api/rooms/{room} 编辑 */
    public function update(Request $request, Room $room): JsonResponse
    {
        $data = $request->validate([
            'room_code' => ['required', 'string', 'max:50', 'unique:rooms,room_code,'.$room->id],
            'room_no' => ['required', 'string', 'max:50'],
            'type' => ['required', 'in:dorm,kitchen,warehouse,drying,office,public'],
            'beds' => ['required', 'integer', 'min:0'],
            'remark' => ['nullable', 'string', 'max:500'],
            'furnitures' => ['nullable', 'array'],
            'furnitures.*.kind' => ['required_with:furnitures', 'string', 'max:50'],
            'furnitures.*.count' => ['required_with:furnitures', 'integer', 'min:0'],
        ], [
            'room_code.unique' => '该房间编码已存在',
        ]);

        DB::transaction(function () use ($room, $data) {
            $room->fill([
                'room_code' => $data['room_code'],
                'room_no' => $data['room_no'],
                'type' => $data['type'],
                'beds' => $data['beds'],
                'remark' => $data['remark'] ?? null,
            ])->save();

            $this->syncFurnitures($room, $data['furnitures'] ?? []);
        });

        return $this->ok($this->toRow($room->refresh()), '已保存');
    }

    /** DELETE /api/rooms/{room} 删除（有在住学生的不能删） */
    public function destroy(Room $room): JsonResponse
    {
        $living = $room->accommodations()->where('status', 'living')->count();
        if ($living > 0) {
            return $this->fail(3001, "该房间还有 {$living} 名在住学生，不能删除");
        }

        DB::transaction(function () use ($room) {
            RoomFurniture::where('room_id', $room->id)->delete();
            $room->delete();
        });

        return $this->ok(null, '已删除');
    }

    /** 同步房间家具（全量覆盖，按 kind 去重） */
    private function syncFurnitures(Room $room, array $items): void
    {
        $rows = [];
        foreach ($items as $it) {
            $kind = trim((string) ($it['kind'] ?? ''));
            if ($kind === '') {
                continue;
            }
            $rows[$kind] = (int) ($it['count'] ?? 0);
        }

        RoomFurniture::where('room_id', $room->id)->delete();
        foreach ($rows as $kind => $count) {
            RoomFurniture::create(['room_id' => $room->id, 'kind' => $kind, 'count' => $count]);
        }
    }

    private function toRow(Room $room): array
    {
        $furnitures = $room->furnitures->map(fn ($f) => [
            'kind' => $f->kind,
            'count' => $f->count,
        ])->all();

        return [
            'id' => $room->id,
            'room_code' => $room->room_code,
            'room_no' => $room->room_no,
            'type' => $room->type->value,
            'type_label' => $room->type->label(),
            'beds' => $room->beds,
            'furnitures' => $furnitures,
            'furniture_text' => $furnitures
                ? implode('；', array_map(fn ($f) => $f['kind'].'×'.$f['count'], $furnitures))
                : null,
            'living_count' => $room->livingCount(),
            'remark' => $room->remark,
            'created_at' => $room->created_at?->format('Y-m-d H:i'),
            'updated_at' => $room->updated_at?->format('Y-m-d H:i'),
        ];
    }
}
