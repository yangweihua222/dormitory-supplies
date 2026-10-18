<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomFurniture extends Model
{
    use HasFactory;

    // furniture 是不可数名词，Eloquent 会推断成单数 room_furniture，需显式指定
    protected $table = 'room_furnitures';

    protected $fillable = ['room_id', 'kind', 'count'];

    protected $casts = [
        'count' => 'integer',
    ];

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }
}
