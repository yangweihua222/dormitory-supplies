<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AttendanceRecord extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id', 'shift_id', 'date', 'plan_check_in_at', 'plan_check_out_at',
        'check_in_at', 'check_out_at', 'late_minutes', 'early_leave_minutes',
        'mark', 'returned_cards', 'returned_keys', 'is_confirmed', 'leave_id', 'remark',
    ];

    protected $casts = [
        'date' => 'date',
        'plan_check_in_at' => 'datetime',
        'plan_check_out_at' => 'datetime',
        'check_in_at' => 'datetime',
        'check_out_at' => 'datetime',
        'is_confirmed' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }
}
