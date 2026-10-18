<?php

namespace App\Enums;

/** 住宿状态：在住 / 已搬出（历史） */
enum AccommodationStatus: string
{
    case LIVING = 'living';
    case MOVED_OUT = 'moved_out';

    public function label(): string
    {
        return match ($this) {
            self::LIVING => '在住',
            self::MOVED_OUT => '已搬出',
        };
    }

    /** @return array<array{value:string,label:string}> */
    public static function options(): array
    {
        return array_map(fn (self $t) => ['value' => $t->value, 'label' => $t->label()], self::cases());
    }
}
