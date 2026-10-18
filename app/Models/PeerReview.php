<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 同行评议（需求 10.3，与原型「同行评议」一致）。
 * scores：5 维度得分（0–10），顺序与 AssessmentDict::PEER_DIMS 对齐；total 满分 50。
 */
class PeerReview extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'reviewer_id', 'reviewee_id', 'month', 'scores', 'total', 'remark', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'scores' => 'array',
            'total' => 'integer',
        ];
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function reviewee()
    {
        return $this->belongsTo(User::class, 'reviewee_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
