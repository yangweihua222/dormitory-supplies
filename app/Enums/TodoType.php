<?php

namespace App\Enums;

enum TodoType: string
{
    case HANDOVER = 'handover';
    case REPAIR = 'repair';
    case SCRAP = 'scrap';
    case HANDOVER_OVERDUE = 'handover_overdue';
    case QTY_MISMATCH = 'qty_mismatch';
    case CARD_RETURN = 'card_return';

    public function label(): string
    {
        return match ($this) {
            self::HANDOVER => '交接',
            self::REPAIR => '维修',
            self::SCRAP => '报废提醒',
            self::HANDOVER_OVERDUE => '交接超时提醒',
            self::QTY_MISMATCH => '数量不符',
            self::CARD_RETURN => '房卡归还',
        };
    }
}
