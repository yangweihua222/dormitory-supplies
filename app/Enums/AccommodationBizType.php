<?php

namespace App\Enums;

/** 住宿业务类型（数据库设计 §13） */
enum AccommodationBizType: string
{
    case CHECKIN = 'checkin';
    case CHECKOUT = 'checkout';
    case TRANSFER = 'transfer';

    public function label(): string
    {
        return match ($this) {
            self::CHECKIN => '入住',
            self::CHECKOUT => '退宿',
            self::TRANSFER => '调宿',
        };
    }

    /** el-tag 类型 */
    public function tagType(): string
    {
        return match ($this) {
            self::CHECKIN => 'primary',
            self::CHECKOUT => 'warning',
            self::TRANSFER => 'info',
        };
    }

    /** @return array<array{value:string,label:string}> */
    public static function options(): array
    {
        return array_map(fn (self $t) => ['value' => $t->value, 'label' => $t->label()], self::cases());
    }
}
