<?php

namespace App\Enums;

/** 房源类型（数据库设计 §11） */
enum RoomType: string
{
    case DORM = 'dorm';
    case KITCHEN = 'kitchen';
    case WAREHOUSE = 'warehouse';
    case DRYING = 'drying';
    case OFFICE = 'office';
    case PUBLIC = 'public';

    public function label(): string
    {
        return match ($this) {
            self::DORM => '宿舍',
            self::KITCHEN => '公共厨房',
            self::WAREHOUSE => '库房',
            self::DRYING => '晾衣间',
            self::OFFICE => '办公室',
            self::PUBLIC => '公共区域',
        };
    }

    /** @return array<array{value:string,label:string}> */
    public static function options(): array
    {
        return array_map(fn (self $t) => ['value' => $t->value, 'label' => $t->label()], self::cases());
    }
}
