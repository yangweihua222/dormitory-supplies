<?php

namespace App\Enums;

enum DutyLogItemCategory: string
{
    case PHONE = 'phone';
    case CONSULT = 'consult';
    case ABNORMAL = 'abnormal';

    public function label(): string
    {
        return match ($this) {
            self::PHONE => '电话接听',
            self::CONSULT => '咨询接待',
            self::ABNORMAL => '异常情况',
        };
    }

    public static function options(): array
    {
        return array_map(fn (self $c) => ['value' => $c->value, 'label' => $c->label()], self::cases());
    }
}
