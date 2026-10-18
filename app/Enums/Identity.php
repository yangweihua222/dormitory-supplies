<?php

namespace App\Enums;

/**
 * 用户身份（需求文档 §三：仅作标签，不直接决定菜单可见性）
 */
enum Identity: string
{
    case Admin = 'admin';
    case Supervisor = 'supervisor';
    case Staff = 'staff';

    public function label(): string
    {
        return match ($this) {
            self::Admin => '管理员',
            self::Supervisor => '主管',
            self::Staff => '工作人员',
        };
    }

    /** 前端下拉选项（与 GET /api/dicts 同源） */
    public static function options(): array
    {
        return array_map(
            fn (self $c) => ['value' => $c->value, 'label' => $c->label()],
            self::cases()
        );
    }
}
