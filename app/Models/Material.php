<?php

namespace App\Models;

use App\Enums\ConsumableSub;
use App\Enums\MaterialKind;
use App\Enums\MaterialUse;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** 物资列表 / 台账（需求 §8 子模块①） */
class Material extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'kind', 'consumable_sub', 'name', 'purchase_year', 'scrap_year',
        'scrapped', 'actual_scrap_year', 'location', 'use_type', 'furniture_kind',
        'code', 'qty', 'stock_in_at', 'stock_in_by', 'stock_out_at', 'stock_out_by',
        'stock', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'kind' => MaterialKind::class,
            'consumable_sub' => ConsumableSub::class,
            'use_type' => MaterialUse::class,
            'scrapped' => 'boolean',
            'purchase_year' => 'integer',
            'scrap_year' => 'integer',
            'actual_scrap_year' => 'integer',
            'qty' => 'integer',
            'stock' => 'integer',
            'stock_in_at' => 'date:Y-m-d',
            'stock_out_at' => 'date:Y-m-d',
        ];
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** 列表「种类」列的展示口径（原型：固定资产 / 耗材-床品类） */
    public function kindLabel(): string
    {
        if ($this->kind === MaterialKind::CONSUMABLE && $this->consumable_sub) {
            return '耗材-'.$this->consumable_sub->label();
        }

        return $this->kind->label();
    }
}
