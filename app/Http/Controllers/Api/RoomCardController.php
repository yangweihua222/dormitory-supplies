<?php

namespace App\Http\Controllers\Api;

use App\Enums\TodoStatus;
use App\Enums\TodoType;
use App\Http\Controllers\Controller;
use App\Models\RoomCard;
use App\Models\Todo;
use App\Models\TodoAssignee;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * 房卡管理（需求 §5.3）
 * 单表 + type 区分三种业务：借用 / 代售 / 退宿
 *
 * 联动：借用登记「房卡交回情况 = 未还」→ 立即生成一条「房卡归还提醒」待办并派给创建人，
 * 待办中写明应归还时间 = 登记时间 + 1 小时；改为「已还」或删除记录 → 撤销该待办。
 */
class RoomCardController extends Controller
{
    use ApiResponse;

    /** GET /api/room-cards?type=borrow|resell|checkout&room_no=&keyword= */
    public function index(Request $request): JsonResponse
    {
        $query = RoomCard::with(['room', 'creator'])->where('type', $request->input('type', 'borrow'));

        if ($roomNo = trim((string) $request->input('room_no', ''))) {
            $query->whereHas('room', fn ($q) => $q->where('room_no', 'like', "%{$roomNo}%"));
        }
        if ($kw = trim((string) $request->input('keyword', ''))) {
            $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$kw}%")
                ->orWhere('student_no', 'like', "%{$kw}%"));
        }

        $paginator = $query->orderByDesc('happened_at')->orderByDesc('id')
            ->paginate((int) $request->input('pageSize', 10));

        $paginator->setCollection($paginator->getCollection()->map(fn ($c) => $this->toRow($c)));

        return $this->page($paginator);
    }

    /** POST /api/room-cards */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate($this->rules($request->input('type')), $this->messages());

        $card = DB::transaction(function () use ($data, $user) {
            $card = RoomCard::create([
                'type' => $data['type'],
                'happened_at' => $data['happened_at'] ?? null,
                'room_id' => $data['room_id'] ?? null,
                'total_card' => $data['total_card'] ?? null,
                'student_no' => $data['student_no'] ?? null,
                'name' => $data['name'] ?? null,
                'contact' => $data['contact'] ?? null,
                'is_returned' => $data['is_returned'] ?? null,
                'pay_method' => $data['pay_method'] ?? null,
                'quantity' => $data['quantity'] ?? null,
                'card_status' => $data['card_status'] ?? null,
                'created_by' => $user->id,
            ]);

            $this->syncReturnTodo($card, $user->id);

            return $card;
        });

        $dueAt = $card->type->value === 'borrow' && $card->is_returned === false
            ? $this->pendingRemindAt($card, 'H:i')
            : null;

        return $this->ok(
            $this->toRow($card),
            $dueAt ? "已添加，已生成归还提醒（于 {$dueAt} 归还）" : '已添加'
        );
    }

    /** PUT /api/room-cards/{roomCard} */
    public function update(Request $request, RoomCard $roomCard): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate($this->rules($request->input('type', $roomCard->type->value)), $this->messages());

        DB::transaction(function () use ($roomCard, $data, $user) {
            $roomCard->fill([
                'type' => $data['type'],
                'happened_at' => $data['happened_at'] ?? null,
                'room_id' => $data['room_id'] ?? null,
                'total_card' => $data['total_card'] ?? null,
                'student_no' => $data['student_no'] ?? null,
                'name' => $data['name'] ?? null,
                'contact' => $data['contact'] ?? null,
                'is_returned' => $data['is_returned'] ?? null,
                'pay_method' => $data['pay_method'] ?? null,
                'quantity' => $data['quantity'] ?? null,
                'card_status' => $data['card_status'] ?? null,
                // 创建人一旦确定即固定：提醒始终发给最初登记这条数据的人
                'created_by' => $roomCard->created_by ?? $user->id,
            ])->save();

            $this->syncReturnTodo($roomCard->refresh(), $user->id);
        });

        return $this->ok($this->toRow($roomCard->refresh()), '已保存');
    }

    /** DELETE /api/room-cards/{roomCard} */
    public function destroy(RoomCard $roomCard): JsonResponse
    {
        DB::transaction(function () use ($roomCard) {
            // 撤销该房卡记录未完成的归还提醒
            Todo::where('type', TodoType::CARD_RETURN)
                ->where('source_type', RoomCard::class)
                ->where('source_id', $roomCard->id)
                ->delete();

            $roomCard->delete();
        });

        return $this->ok(null, '已删除');
    }

    /** 该记录当前的应归还时间（未还且有待办时返回，供列表展示 / 提示文案） */
    private function pendingRemindAt(RoomCard $c, string $format = 'Y-m-d H:i'): ?string
    {
        if ($c->type->value !== 'borrow' || $c->is_returned !== false) {
            return null;
        }

        return Todo::where('type', TodoType::CARD_RETURN)
            ->where('source_type', RoomCard::class)
            ->where('source_id', $c->id)
            ->where('status', TodoStatus::PENDING)
            ->first()?->remind_at?->format($format);
    }

    /**
     * 同步「房卡归还提醒」待办（仅借用业务）
     * - 借用 + 未还 → 立即建/续一条待办派给创建人，待办里写明「于 H:i 归还」（登记时间 + 1 小时）
     * - 借用 + 已还（或其他业务）→ 撤销尚未完成的提醒
     */
    private function syncReturnTodo(RoomCard $card, ?int $operatorId): void
    {
        $todo = Todo::where('type', TodoType::CARD_RETURN)
            ->where('source_type', RoomCard::class)
            ->where('source_id', $card->id)
            ->where('status', TodoStatus::PENDING)
            ->first();

        $needRemind = $card->type->value === 'borrow' && $card->is_returned === false;

        if (! $needRemind) {
            $todo?->delete();

            return;
        }

        $dueAt = now()->addHour();

        if (! $todo) {
            $todo = Todo::create([
                'type' => TodoType::CARD_RETURN,
                'title' => sprintf(
                    '房卡归还提醒：%s %s（于 %s 归还）',
                    $card->room?->room_no ?? '未指定房间',
                    $card->name ?: '未填写借卡人',
                    $dueAt->format('H:i'),
                ),
                'content' => sprintf(
                    '借卡人：%s%s学号：%s%s联系方式：%s%s借用日期：%s%s请于 %s 归还房卡。',
                    $card->name ?: '—',
                    PHP_EOL,
                    $card->student_no ?: '—',
                    PHP_EOL,
                    $card->contact ?: '—',
                    PHP_EOL,
                    $card->happened_at?->format('Y-m-d') ?? '—',
                    PHP_EOL,
                    $dueAt->format('H:i'),
                ),
                'status' => TodoStatus::PENDING,
                'source_type' => RoomCard::class,
                'source_id' => $card->id,
                'created_by' => $card->created_by ?? $operatorId,
                'remind_at' => $dueAt,
            ]);
        } else {
            // 仍是未还：按新的保存时间刷新应归还时间，并同步标题里的时刻
            $todo->remind_at = $dueAt;
            $todo->title = sprintf(
                '房卡归还提醒：%s %s（于 %s 归还）',
                $card->room?->room_no ?? '未指定房间',
                $card->name ?: '未填写借卡人',
                $dueAt->format('H:i'),
            );
            $todo->save();
        }

        // 提醒对象固定为创建人
        $ownerId = $card->created_by ?? $operatorId;
        if ($ownerId) {
            TodoAssignee::where('todo_id', $todo->id)->delete();
            TodoAssignee::create(['todo_id' => $todo->id, 'user_id' => $ownerId]);
        }
    }

    private function rules(?string $type): array
    {
        $common = [
            'type' => ['required', 'in:borrow,resell,checkout'],
            'happened_at' => ['required', 'date'],
            'room_id' => ['required', 'exists:rooms,id'],
            'student_no' => ['required', 'string', 'max:50'],
        ];

        return match ($type) {
            'borrow' => $common + [
                'total_card' => ['required', 'string', 'max:50'],
                'name' => ['required', 'string', 'max:50'],
                'contact' => ['required', 'string', 'max:50'],
                'is_returned' => ['required', 'boolean'],
            ],
            'resell' => $common + [
                'contact' => ['required', 'string', 'max:50'],
                'pay_method' => ['required', 'in:alipay,wechat'],
                'quantity' => ['required', 'integer', 'min:1'],
            ],
            default => $common + [
                'quantity' => ['required', 'integer', 'min:1'],
                'is_returned' => ['required', 'boolean'],
                'card_status' => ['required', 'in:disabled,active'],
            ],
        };
    }

    private function messages(): array
    {
        return [
            'type.required' => '请选择房卡类型',
            'happened_at.required' => '请选择日期',
            'room_id.required' => '请选择房间号',
            'student_no.required' => '请填写学号',
            'total_card.required' => '请选择房间总卡',
            'name.required' => '请填写借卡人姓名',
            'contact.required' => '请填写联系方式',
            'is_returned.required' => '请选择房卡交回情况',
            'pay_method.required' => '请选择支付方式',
            'quantity.required' => '请填写数量',
            'card_status.required' => '请选择卡状态',
        ];
    }

    private function toRow(RoomCard $c): array
    {
        return [
            'id' => $c->id,
            'type' => $c->type->value,
            'type_label' => $c->type->label(),
            'happened_at' => $c->happened_at?->format('Y-m-d'),
            'room_id' => $c->room_id,
            'room_no' => $c->room?->room_no,
            'total_card' => $c->total_card,
            'student_no' => $c->student_no,
            'name' => $c->name,
            'contact' => $c->contact,
            'is_returned' => $c->is_returned,
            'is_returned_label' => is_null($c->is_returned) ? null : ($c->is_returned ? '已还' : '未还'),
            'pay_method' => $c->pay_method,
            'pay_method_label' => match ($c->pay_method) {
                'alipay' => '支付宝',
                'wechat' => '微信',
                default => null,
            },
            'quantity' => $c->quantity,
            'card_status' => $c->card_status,
            'card_status_label' => match ($c->card_status) {
                'disabled' => '停用',
                'active' => '未停用',
                default => null,
            },
            'created_at' => $c->created_at?->format('Y-m-d H:i'),
            'updated_at' => $c->updated_at?->format('Y-m-d H:i'),
            'creator' => $c->creator?->username,
            'remind_at' => $this->pendingRemindAt($c),
        ];
    }
}
