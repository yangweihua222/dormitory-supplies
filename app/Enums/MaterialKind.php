<?php

namespace App\Enums;

/** 物资种类（需求 §8）：固定资产 / 耗材 */
enum MaterialKind: string
{
    case FIXED = 'fixed';
    case CONSUMABLE = 'consumable';

    public function label(): string
    {
        return match ($this) {
            self::FIXED => '固定资产',
            self::CONSUMABLE => '耗材',
        };
    }

    /** 入库/出库流水的类型展示口径（原型：耗材类） */
    public function stockLabel(): string
    {
        return match ($this) {
            self::FIXED => '固定资产',
            self::CONSUMABLE => '耗材类',
        };
    }

    public static function options(): array
    {
        return array_map(fn ($c) => ['value' => $c->value, 'label' => $c->label()], self::cases());
    }
}
