<?php

namespace App\Enums;

/** 房卡业务类型（数据库设计 §14） */
enum RoomCardType: string
{
    case BORROW = 'borrow';
    case RESELL = 'resell';
    case CHECKOUT = 'checkout';

    public function label(): string
    {
        return match ($this) {
            self::BORROW => '借用',
            self::RESELL => '代售',
            self::CHECKOUT => '退宿',
        };
    }

    /** @return array<array{value:string,label:string}> */
    public static function options(): array
    {
        return array_map(fn (self $t) => ['value' => $t->value, 'label' => $t->label()], self::cases());
    }
}
