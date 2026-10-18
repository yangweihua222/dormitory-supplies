<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeaveBalance extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'total_days', 'remain_days'];

    protected $casts = [
        'total_days' => 'float',
        'remain_days' => 'float',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
