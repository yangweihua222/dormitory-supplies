<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** 消防培训（需求 §7.3） */
class FireTraining extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'train_date', 'trainer_name', 'student_no', 'grade',
        'train_content', 'train_hours', 'remark', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'train_date' => 'date:Y-m-d',
            'train_hours' => 'float',
        ];
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
