<?php

namespace App\Enums;

/** 耗材子类（需求 §8）：家电类 / 床品类 / 清洁类 / 洗化类 / 奖品类 / 其他 */
enum ConsumableSub: string
{
    case APPLIANCE = 'appliance';
    case BEDDING = 'bedding';
    case CLEANING = 'cleaning';
    case TOILETRIES = 'toiletries';
    case PRIZE = 'prize';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::APPLIANCE => '家电类',
            self::BEDDING => '床品类',
            self::CLEANING => '清洁类',
            self::TOILETRIES => '洗化类',
            self::PRIZE => '奖品类',
            self::OTHER => '其他',
        };
    }

    public static function options(): array
    {
        return array_map(fn ($c) => ['value' => $c->value, 'label' => $c->label()], self::cases());
    }
}
