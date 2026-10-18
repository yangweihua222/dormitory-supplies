<?php

namespace App\Enums;

/** 检查结果（检查类安全记录：正常 / 异常） */
enum CheckResult: string
{
    case NORMAL = 'normal';
    case ABNORMAL = 'abnormal';

    public function label(): string
    {
        return match ($this) {
            self::NORMAL => '正常',
            self::ABNORMAL => '异常',
        };
    }

    public function tagType(): string
    {
        return match ($this) {
            self::NORMAL => 'success',
            self::ABNORMAL => 'danger',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->map(fn ($c) => ['value' => $c->value, 'label' => $c->label()])
            ->all();
    }
}
