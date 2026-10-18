<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** 消防志愿服务（需求 §7.2） */
class FireVolunteer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'student_name', 'student_no', 'grade', 'service_date',
        'service_content', 'service_hours', 'remark', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'service_date' => 'date:Y-m-d',
            'service_hours' => 'float',
        ];
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
