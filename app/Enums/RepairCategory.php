<?php

namespace App\Enums;

/** 报修类别（大类，需求 §6；子类见 RepairCategory::subCategories()） */
enum RepairCategory: string
{
    case WATER = 'water';
    case WOOD = 'wood';
    case ELECTRIC = 'electric';
    case AC = 'ac';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::WATER => '水工',
            self::WOOD => '木工',
            self::ELECTRIC => '电工',
            self::AC => '空调',
            self::OTHER => '其他',
        };
    }

    /** @return array<array{value:string,label:string}> */
    public static function options(): array
    {
        return array_map(fn (self $c) => ['value' => $c->value, 'label' => $c->label()], self::cases());
    }

    /** 大类 → 子类（报修项目），与原型 data.js repairCats 完全一致 */
    public static function subCategories(): array
    {
        return [
            self::WATER->value => ['堵塞', '漏水', '其他'],
            self::WOOD->value => [
                '零星维修-墙面', '零星维修-地面', '零星维修-吊顶', '零星维修-五金',
                '设施类-门', '设施类-锁', '设施类-窗', '家具类-桌椅床柜',
            ],
            self::ELECTRIC->value => ['照明（灯管灯泡）', '插座、开关', '突发断电', '排、换气扇'],
            self::AC->value => ['温度调节', '漏水', '控制面板', '排风口'],
            self::OTHER->value => ['其他'],
        ];
    }

    /** @return array<string> */
    public function subOptions(): array
    {
        return self::subCategories()[$this->value] ?? [];
    }
}
