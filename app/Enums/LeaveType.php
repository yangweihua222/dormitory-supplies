<?php

namespace App\Enums;

enum LeaveType: string
{
    case ANNUAL = 'annual';
    case PERSONAL = 'personal';
    case SICK = 'sick';

    public function label(): string
    {
        return match ($this) {
            self::ANNUAL => '年假',
            self::PERSONAL => '事假',
            self::SICK => '病假',
        };
    }

    /** @return array<array{value:string,label:string}> */
    public static function options(): array
    {
        return array_map(
            fn (self $t) => ['value' => $t->value, 'label' => $t->label()],
            self::cases()
        );
    }
}
