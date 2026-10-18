<?php

namespace App\Enums;

enum ScheduleStatus: string
{
    case NORMAL = 'normal';
    case LEAVE = 'leave';

    public function label(): string
    {
        return match ($this) {
            self::NORMAL => '正常',
            self::LEAVE => '请假',
        };
    }
}
