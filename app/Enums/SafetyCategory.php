<?php

namespace App\Enums;

/**
 * 安全检查类别（对齐原型 6 个类别：宿舍消防安全检查/公共秩序/财产安全/人身安全/环境卫生安全/突发类安全事件；
 * 需求 §7.1.2「公寓消防安全隐患排查」原型未包含，不做；§7.1.8「房源排查」已从原型移除，不做）
 *
 * kind 决定表单与列表渲染哪套字段：
 * - check：逐房间用电安全检查（房间号 / 检查人员 / 外来排查人员 / 6 项用电检查 / 检查结果 / 整改状态 / 备注）
 * - event：事件类（事件描述 / 时间 / 地点 / 人物 / 经过 / 图片）
 * - emergency：突发类（事件类字段 + 事件类别）
 */
enum SafetyCategory: string
{
    case DORM_FIRE = 'dorm_fire';
    case PUBLIC_ORDER = 'public_order';
    case PROPERTY = 'property';
    case PERSONAL = 'personal';
    case ENVIRONMENT = 'environment';
    case EMERGENCY = 'emergency';

    public function label(): string
    {
        return match ($this) {
            self::DORM_FIRE => '宿舍消防安全检查',
            self::PUBLIC_ORDER => '公共秩序',
            self::PROPERTY => '财产安全',
            self::PERSONAL => '人身安全',
            self::ENVIRONMENT => '环境卫生安全',
            self::EMERGENCY => '突发类安全事件',
        };
    }

    /** 表单形态：check = 检查类，event = 事件类，emergency = 突发类事件 */
    public function kind(): string
    {
        return match ($this) {
            self::DORM_FIRE => 'check',
            self::EMERGENCY => 'emergency',
            default => 'event',
        };
    }

    public function isCheck(): bool
    {
        return $this->kind() === 'check';
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->map(fn ($c) => [
                'value' => $c->value,
                'label' => $c->label(),
                'kind' => $c->kind(),
            ])
            ->all();
    }
}
