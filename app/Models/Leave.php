<?php

namespace App\Models;

use App\Enums\ApproveStatus;
use App\Enums\LeaveType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Leave extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'applied_at',
        'type',
        'reason',
        'start_date',
        'end_date',
        'substitute_user_id',
        'approver_id',
        'approve_status',
        'approved_at',
    ];

    protected $casts = [
        'applied_at' => 'datetime',
        'approved_at' => 'datetime',
        'start_date' => 'date',
        'end_date' => 'date',
        'type' => LeaveType::class,
        'approve_status' => ApproveStatus::class,
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function substitute()
    {
        return $this->belongsTo(User::class, 'substitute_user_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class, 'related_id')->where('related_type', 'leave');
    }

    /** 请假天数（含首尾） */
    public function days(): int
    {
        return (int) $this->start_date->diffInDays($this->end_date) + 1;
    }
}
