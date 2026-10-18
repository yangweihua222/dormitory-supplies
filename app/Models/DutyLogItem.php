<?php

namespace App\Models;

use App\Enums\DutyLogItemCategory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DutyLogItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'duty_log_id',
        'category',
        'caller',
        'is_need_callback',
        'contact',
        'is_recalled',
        'visitor',
        'matter',
        'is_need_transfer',
        'transfer_to',
        'is_finished',
        'description',
        'is_reported',
        'handling',
    ];

    protected $casts = [
        'category' => DutyLogItemCategory::class,
        'is_need_callback' => 'boolean',
        'is_recalled' => 'boolean',
        'is_need_transfer' => 'boolean',
        'is_finished' => 'boolean',
        'is_reported' => 'boolean',
    ];

    public function dutyLog()
    {
        return $this->belongsTo(DutyLog::class);
    }
}
