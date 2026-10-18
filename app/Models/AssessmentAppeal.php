<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 考核申诉（需求 10.2，与原型「考核申诉」一致）。
 * status：pending 申诉中 / done 已完成（管理员填写处理结果后流转）。
 */
class AssessmentAppeal extends Model
{
    use SoftDeletes;

    public const STATUS_PENDING = 'pending';

    public const STATUS_DONE = 'done';

    protected $fillable = [
        'user_id', 'month', 'matter', 'appeal_date',
        'result', 'rectify', 'status', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'appeal_date' => 'datetime:Y-m-d H:i',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function statusLabel(): string
    {
        return $this->status === self::STATUS_DONE ? '已完成' : '申诉中';
    }
}
