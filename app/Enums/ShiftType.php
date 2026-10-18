<?php

namespace App\Enums;

enum ShiftType: string
{
    case DUTY = 'duty';
    case NORMAL = 'normal';

    public function label(): string
    {
        return match ($this) {
            self::DUTY => '值班',
            self::NORMAL => '正常班',
        };
    }
}
