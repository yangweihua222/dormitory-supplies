<?php

namespace App\Enums;

enum RepairStatus: string
{
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case DONE = 'done';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => '待处理',
            self::PROCESSING => '处理中',
            self::DONE => '已完成',
        };
    }

    public function tagType(): string
    {
        return match ($this) {
            self::PENDING => 'warning',
            self::PROCESSING => 'primary',
            self::DONE => 'success',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->map(fn ($c) => ['value' => $c->value, 'label' => $c->label()])
            ->all();
    }
}
