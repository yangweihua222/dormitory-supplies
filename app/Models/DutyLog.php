<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DutyLog extends Model
{
    protected $fillable = [
        'log_date',
        'category',
        'detail',
        'creator_id',
        'status',
        'handoff_done',
        'handed_over_at',
    ];

    protected $casts = [
        'log_date' => 'date',
        'detail' => 'array',
        'handoff_done' => 'boolean',
        'handed_over_at' => 'datetime',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id');
    }
}
