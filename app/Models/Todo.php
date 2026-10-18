<?php

namespace App\Models;

use App\Enums\RepairStatus;
use App\Enums\TodoStatus;
use App\Enums\TodoType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Todo extends Model
{
    use HasFactory, SoftDeletes;

    /** 工作台待办每页条数 */
    public const PAGE_SIZE = 10;

    protected $fillable = [
        'type', 'title', 'content', 'status', 'source_type', 'source_id',
        'created_by', 'first_handover_at', 'completed_at',
        'remind_at', // 房卡归还待办：应归还时间（借用登记时间 + 1 小时），仅用于文案展示；其他类型为空
    ];

    protected $casts = [
        'type' => TodoType::class,
        'status' => TodoStatus::class,
        'first_handover_at' => 'datetime',
        'completed_at' => 'datetime',
        'remind_at' => 'datetime',
    ];

    public function assignees()
    {
        return $this->hasMany(TodoAssignee::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'todo_assignees');
    }

    public function source()
    {
        return $this->morphTo();
    }

    public function scopeForUser($query, User $user)
    {
        return $query->whereHas('assignees', fn ($q) => $q->where('user_id', $user->id));
    }

    /**
     * 待办流：未完成在前（创建时间倒序），已完成紧随其后（完成时间倒序）
     * 数据范围：被指派人本人；管理员额外可见系统提醒类（报废 / 交接超时 / 数量不符）
     */
    public static function feed(User $user, int $page = 1, int $size = self::PAGE_SIZE): array
    {
        $pending = self::with('assignees.user')
            ->visibleTo($user)
            ->where('status', TodoStatus::PENDING)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        $done = self::with('assignees.user')
            ->visibleTo($user)
            ->where('status', TodoStatus::COMPLETED)
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->get();

        $all = $pending->concat($done);
        $total = $all->count();
        $pages = max(1, (int) ceil($total / $size));
        $page = max(1, min($page, $pages));
        $list = $all->slice(($page - 1) * $size, $size)->values();

        $rate = $total > 0 ? round($done->count() / $total * 100) : 0;

        return [
            'list' => $list->map(fn ($t) => [
                'id' => $t->id,
                'type' => $t->type->value,
                'type_label' => $t->type->label(),
                'title' => $t->title,
                'content' => $t->content,
                'status' => $t->status->value,
                'status_label' => $t->status->label(),
                'created_at' => $t->created_at?->format('m-d H:i'),
                'completed_at' => $t->completed_at?->format('m-d H:i'),
                'remind_at' => $t->remind_at?->format('m-d H:i'),
                'assignees' => $t->assignees->map(fn ($a) => ['id' => $a->user_id, 'name' => $a->user?->username])->toArray(),
                // 点待办直达来源记录（当前仅值班日志支持定位高亮）
                'link' => $t->source_type === DutyLog::class && $t->source_id
                    ? "/dutylog?focus={$t->source_id}"
                    : null,
            ])->all(),
            'pending' => $pending->count(),
            'done' => $done->count(),
            'total' => $total,
            'rate' => $rate,
            'month_handover' => self::monthHandoverCount($user),
            'page' => $page,
            'pages' => $pages,
            'size' => $size,
        ];
    }

    /**
     * 数据范围（需求 §1.1）：管理员看全部；普通员工 / 主管看本人（指派给自己的）；
     * 徐长东额外可见物资类系统提醒（报废提醒 / 数量不符）
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->isAdmin()) {
            return;
        }

        $query->where(function ($q) use ($user) {
            $q->whereHas('assignees', fn ($sq) => $sq->where('user_id', $user->id));
            if ($user->canSeeMaterialAlerts()) {
                $q->orWhereIn('type', [TodoType::SCRAP, TodoType::QTY_MISMATCH]);
            }
        });
    }

    /** 能否操作（点完成）某条待办：与可见范围同一口径 */
    public static function canOperate(User $user, self $todo): bool
    {
        if ($user->isAdmin()) {
            return true;
        }
        if ($todo->assignees->contains('user_id', $user->id)) {
            return true;
        }

        return $user->canSeeMaterialAlerts()
            && in_array($todo->type, [TodoType::SCRAP, TodoType::QTY_MISMATCH], true);
    }

    /** 当月交接数量：管理员看全部人，其他角色看本人（需求 §1.1） */
    public static function monthHandoverCount(User $user): int
    {
        $query = self::whereIn('type', [TodoType::HANDOVER, TodoType::HANDOVER_OVERDUE])
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()]);

        if (! $user->isAdmin()) {
            $query->whereHas('assignees', fn ($sq) => $sq->where('user_id', $user->id));
        }

        return $query->count();
    }

    protected static function booted(): void
    {
        // 待办被点「完成」→ 反写来源业务的状态（需求 §1.1 / §六）
        static::updated(function (Todo $todo) {
            if (! $todo->wasChanged('status') || $todo->status !== TodoStatus::COMPLETED) {
                return;
            }
            if (! $todo->source_type || ! $todo->source_id) {
                return;
            }

            // 维修类 → 报修「维修情况」置「已完成」
            if ($todo->type === TodoType::REPAIR && $todo->source_type === Repair::class) {
                Repair::whereKey($todo->source_id)
                    ->where('repair_status', '!=', RepairStatus::DONE)
                    ->update(['repair_status' => RepairStatus::DONE]);

                return;
            }

            // 交接类 → 值班日志置「已完成」/ 住宿记录置「手续已办理」
            if ($todo->type === TodoType::HANDOVER) {
                if ($todo->source_type === DutyLog::class) {
                    DutyLog::whereKey($todo->source_id)
                        ->where('status', '!=', 'done')
                        ->update([
                            'status' => 'done',
                            'handoff_done' => true,
                            'handed_over_at' => now(),
                        ]);
                } elseif ($todo->source_type === Accommodation::class) {
                    Accommodation::whereKey($todo->source_id)
                        ->where('is_procedure_done', false)
                        ->update(['is_procedure_done' => true]);
                }

                return;
            }

            // 房卡归还提醒 → 房卡交回情况置「已还」
            if ($todo->type === TodoType::CARD_RETURN && $todo->source_type === RoomCard::class) {
                RoomCard::whereKey($todo->source_id)
                    ->where('is_returned', '!=', true)
                    ->update(['is_returned' => true]);
            }
        });
    }
}
