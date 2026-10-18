<?php

namespace App\Enums;

enum ApproveStatus: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => '待审批',
            self::APPROVED => '同意',
            self::REJECTED => '拒绝',
        };
    }

    /** Element Plus tag 类型 */
    public function tagType(): string
    {
        return match ($this) {
            self::PENDING => 'warning',
            self::APPROVED => 'success',
            self::REJECTED => 'danger',
        };
    }

    /** @return array<array{value:string,label:string}> */
    public static function options(): array
    {
        return array_map(
            fn (self $s) => ['value' => $s->value, 'label' => $s->label()],
            self::cases()
        );
    }
}
