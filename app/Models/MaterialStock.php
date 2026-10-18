<?php

namespace App\Models;

use App\Enums\MaterialKind;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** 入库 / 出库流水（需求 §8 子模块②③） */
class MaterialStock extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'direction', 'type', 'category', 'qty', 'code',
        'stock_date', 'manager', 'location', 'remark', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => MaterialKind::class,
            'qty' => 'integer',
            'stock_date' => 'date:Y-m-d',
        ];
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
