<?php

namespace App\Models;

use App\Enums\RoomCardType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class RoomCard extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'type', 'happened_at', 'room_id', 'total_card', 'student_no', 'name',
        'contact', 'is_returned', 'pay_method', 'quantity', 'card_status', 'created_by',
    ];

    protected $casts = [
        'type' => RoomCardType::class,
        'happened_at' => 'date',
        'is_returned' => 'boolean',
        'quantity' => 'integer',
    ];

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /** 创建人：借用未还时的提醒对象 */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
