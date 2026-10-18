<?php

namespace App\Models;

use App\Enums\AppointPeriod;
use App\Enums\RepairCategory;
use App\Enums\RepairStatus;
use App\Enums\TodoStatus;
use App\Enums\TodoType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Repair extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'report_date', 'location', 'category', 'sub_category', 'description',
        'repair_date', 'appoint_period', 'is_agree_proxy', 'contact',
        'is_reported_platform', 'reporter_id', 'follower_id', 'repair_status',
    ];

    protected function casts(): array
    {
        return [
            'category' => RepairCategory::class,
            'repair_status' => RepairStatus::class,
            'appoint_period' => AppointPeriod::class,
            'report_date' => 'date:Y-m-d',
            'repair_date' => 'date:Y-m-d',
            'is_agree_proxy' => 'boolean',
            'is_reported_platform' => 'boolean',
        ];
    }

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function follower()
    {
        return $this->belongsTo(User::class, 'follower_id');
    }

    public function todos()
    {
        return $this->morphMany(Todo::class, 'source');
    }

    /** 该报修下尚未完成的维修待办 */
    public function pendingRepairTodo()
    {
        return $this->morphOne(Todo::class, 'source')
            ->where('type', TodoType::REPAIR)
            ->where('status', TodoStatus::PENDING);
    }
}
