<?php

namespace App\Models;

use App\Support\AssessmentDict;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 月度考核（需求 10.1，与原型「考核管理 · 月度考核」一致）。
 * items：5 个模块的评分明细 [{ded, bon, note}]，顺序与 AssessmentDict::MODULES 对齐；
 * total / grade 由后端根据 items 统一计算，不以前端传入为准。
 */
class Assessment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id', 'month', 'assess_date', 'supervisor_id',
        'items', 'total', 'grade', 'remark', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'assess_date' => 'date:Y-m-d',
            'items' => 'array',
            'total' => 'float',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function supervisor()
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** 按字典模块口径重算总分与等级（items 为 [{ded, bon, note}]） */
    public static function computeTotal(array $items): float
    {
        $total = 0.0;
        foreach (AssessmentDict::MODULES as $i => $m) {
            $ded = max(0, (float) ($items[$i]['ded'] ?? 0));
            $bon = max(0, (float) ($items[$i]['bon'] ?? 0));
            $total += $m['std'] - $ded + $bon;
        }

        return round($total, 1);
    }
}
