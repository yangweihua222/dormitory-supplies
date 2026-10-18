<?php

namespace App\Enums;

/** 突发类安全事件 · 事件类别（需求 §7.1.7） */
enum EmergencyCategory: string
{
    case FIRE = 'fire';
    case HEALTH = 'health';
    case MENTAL = 'mental';
    case DISASTER = 'disaster';
    case MASS = 'mass';

    public function label(): string
    {
        return match ($this) {
            self::FIRE => '火情',
            self::HEALTH => '公共卫生',
            self::MENTAL => '突发精神类',
            self::DISASTER => '自然灾害',
            self::MASS => '群体性',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->map(fn ($c) => ['value' => $c->value, 'label' => $c->label()])
            ->all();
    }
}
