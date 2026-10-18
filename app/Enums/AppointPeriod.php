<?php

namespace App\Enums;

/** 报修预约时间（上午 / 下午，对应原型时段 08:00-11:00 与 14:00-17:00） */
enum AppointPeriod: string
{
    case AM = 'am';
    case PM = 'pm';

    public function label(): string
    {
        return match ($this) {
            self::AM => '上午',
            self::PM => '下午',
        };
    }

    /** 列表展示用时段文本 */
    public function timeRange(): string
    {
        return match ($this) {
            self::AM => '08:00-11:00',
            self::PM => '14:00-17:00',
        };
    }

    /** @return array<array{value:string,label:string}> */
    public static function options(): array
    {
        return array_map(fn (self $p) => ['value' => $p->value, 'label' => $p->label()], self::cases());
    }
}
