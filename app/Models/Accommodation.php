<?php

namespace App\Models;

use App\Enums\AccommodationBizType;
use App\Enums\AccommodationStatus;
use App\Enums\TodoStatus;
use App\Enums\TodoType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Accommodation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'room_id', 'student_no', 'name', 'gender', 'biz_type', 'status',
        'check_in_at', 'check_out_at', 'original_room_no',
        'agreement_status', 'safety_duty_status', 'furniture_confirm',
        'foreigner_registration', 'mattress', 'bedding', 'access_control',
        'has_checkout_order', 'is_belongings_cleared', 'furniture_state',
        'access_action', 'has_transfer_application',
        'is_procedure_done', 'is_handed_over', 'remark',
    ];

    protected $casts = [
        'biz_type' => AccommodationBizType::class,
        'status' => AccommodationStatus::class,
        'check_in_at' => 'datetime',
        'check_out_at' => 'datetime',
        'has_checkout_order' => 'boolean',
        'is_belongings_cleared' => 'boolean',
        'has_transfer_application' => 'boolean',
        'is_procedure_done' => 'boolean',
        'is_handed_over' => 'boolean',
    ];

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * 是否可交接（需求 §5.2.4）
     * - 手续已完成 → 置灰
     * - 交接待办已被值班人员点「完成」→ 置灰
     * - 已交接但待办仍 pending → 可继续交接给后续值班同事（循环往复）
     */
    public function canHandOver(): bool
    {
        if ($this->is_procedure_done) {
            return false;
        }

        return ! Todo::where('type', TodoType::HANDOVER)
            ->where('source_type', self::class)
            ->where('source_id', $this->id)
            ->where('status', TodoStatus::COMPLETED)
            ->exists();
    }

    public function todos()
    {
        return $this->morphMany(Todo::class, 'source');
    }
}
