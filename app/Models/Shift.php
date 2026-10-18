<?php

namespace App\Models;

use App\Enums\ShiftType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Shift extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['name', 'check_in_time', 'check_out_time', 'type'];

    protected $casts = [
        'check_in_time' => 'datetime:H:i:s',
        'check_out_time' => 'datetime:H:i:s',
        'type' => ShiftType::class,
    ];
}
