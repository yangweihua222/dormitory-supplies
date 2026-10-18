<?php

namespace App\Enums;

/** 物资用途（需求 §8）：办公 / 生活 */
enum MaterialUse: string
{
    case OFFICE = 'office';
    case LIFE = 'life';

    public function label(): string
    {
        return match ($this) {
            self::OFFICE => '办公',
            self::LIFE => '生活',
        };
    }

    public static function options(): array
    {
        return array_map(fn ($c) => ['value' => $c->value, 'label' => $c->label()], self::cases());
    }
}
