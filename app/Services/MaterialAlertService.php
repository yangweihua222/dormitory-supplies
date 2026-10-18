<?php

namespace App\Services;

use App\Enums\TodoStatus;
use App\Enums\TodoType;
use App\Models\Material;
use App\Models\Room;
use App\Models\Todo;
use App\Models\TodoAssignee;
use App\Models\User;

/**
 * 物资类系统提醒（需求 §六 联动表）：
 * 1. 报废年限检查——每年 5 月 1 日自动：未报废且预计报废年限 ≤ 当前年的物资 → 报废提醒待办 → 仅管理员与徐长东
 * 2. 房间配置数量不符——落库后生成「数量不符」待办 → 管理员 / 徐长东
 *
 * 接收人集合与维修待办相同（徐长东 ∪ 全部管理员），复用 User::repairTodoRecipients()。
 */
class MaterialAlertService
{
    /**
     * 年度报废检查（幂等）：到 5 月 1 日后才执行；同一物资同一年只提醒一次。
     * 采用懒触发——管理员 / 徐长东拉取待办时调用，无需额外守护进程。
     */
    public static function runAnnualScrapCheck(): void
    {
        $now = now();
        $year = (int) $now->format('Y');
        // 每年 5 月 1 日触发；之前不检查
        if ($now->lt($now->copy()->setDate($year, 5, 1)->startOfDay())) {
            return;
        }

        $due = Material::query()
            ->where('scrapped', false)
            ->whereNotNull('scrap_year')
            ->where('scrap_year', '<=', $year)
            ->get();

        foreach ($due as $mat) {
            // 同一物资：当年已提醒过，或已有未处理的报废提醒，均不重复发
            $exists = Todo::query()
                ->where('type', TodoType::SCRAP)
                ->where('source_type', Material::class)
                ->where('source_id', $mat->id)
                ->where(function ($q) use ($year) {
                    $q->whereYear('created_at', $year)
                        ->orWhere('status', TodoStatus::PENDING);
                })
                ->exists();
            if ($exists) {
                continue;
            }

            $todo = Todo::create([
                'type' => TodoType::SCRAP,
                'title' => "物资报废提醒：{$mat->name}（{$mat->location}）",
                'content' => sprintf(
                    '【物资报废提醒】%s「%s」（资产编号 %s，存放于 %s）预计报废年限为 %d 年，当前已到预计报废年限，请管理员核查是否做报废处置。',
                    $mat->kind->label(),
                    $mat->name,
                    $mat->code ?: '—',
                    $mat->location,
                    $mat->scrap_year,
                ),
                'status' => TodoStatus::PENDING,
                'source_type' => Material::class,
                'source_id' => $mat->id,
            ]);
            foreach (User::repairTodoRecipients() as $userId) {
                TodoAssignee::create(['todo_id' => $todo->id, 'user_id' => $userId]);
            }
        }
    }

    /**
     * 房间配置数量校验（需求 §8 ★真工程）：
     * 放置地点=房间号 且 家具种类匹配到该房间配置 → 比对数量。
     * 返回 ['matched' => bool, 'configured' => int|null]——matched=true 且 configured !== qty 即不一致。
     */
    public static function checkRoomConfig(string $location, ?string $furnitureKind, int $qty): array
    {
        if (! $furnitureKind) {
            return ['matched' => false, 'configured' => null];
        }

        $room = Room::query()->where('room_no', $location)->first();
        if (! $room) {
            return ['matched' => false, 'configured' => null];
        }

        $config = $room->furnitures()->where('kind', $furnitureKind)->first();
        if (! $config) {
            return ['matched' => false, 'configured' => null];
        }

        return ['matched' => true, 'configured' => (int) $config->count];
    }

    /** 数量不符待办（幂等：同一物资已有未完成提醒则更新文案，不重复发） */
    public static function createQtyMismatchTodo(Material $mat, int $configured): void
    {
        $content = sprintf(
            '【数量不符提醒】添加物资「%s」（%s，%s）数量与房间配置不一致：%s配置%s %d 件，本次登记数量为 %d 件，请管理员核对。',
            $mat->name,
            $mat->furniture_kind ?: '—',
            $mat->kind->label(),
            $mat->location,
            $mat->furniture_kind,
            $configured,
            $mat->qty,
        );

        $todo = Todo::query()
            ->where('type', TodoType::QTY_MISMATCH)
            ->where('source_type', Material::class)
            ->where('source_id', $mat->id)
            ->where('status', TodoStatus::PENDING)
            ->first();

        if (! $todo) {
            $todo = Todo::create([
                'type' => TodoType::QTY_MISMATCH,
                'title' => "数量不符：{$mat->name}（{$mat->location}）",
                'content' => $content,
                'status' => TodoStatus::PENDING,
                'source_type' => Material::class,
                'source_id' => $mat->id,
                'created_by' => $mat->created_by,
            ]);
            foreach (User::repairTodoRecipients() as $userId) {
                TodoAssignee::create(['todo_id' => $todo->id, 'user_id' => $userId]);
            }
        } else {
            $todo->update(['content' => $content, 'title' => "数量不符：{$mat->name}（{$mat->location}）"]);
        }
    }
}
