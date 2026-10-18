<?php

namespace App\Enums;

enum TodoStatus: string
{
    case PENDING = 'pending';
    case COMPLETED = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => '待处理',
            self::COMPLETED => '已完成',
        };
    }
}
