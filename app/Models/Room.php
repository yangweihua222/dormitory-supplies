<?php

namespace App\Models;

use App\Enums\RoomType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Room extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['room_code', 'room_no', 'type', 'beds', 'remark'];

    protected $casts = [
        'type' => RoomType::class,
        'beds' => 'integer',
    ];

    public function furnitures(): HasMany
    {
        return $this->hasMany(RoomFurniture::class);
    }

    public function accommodations(): HasMany
    {
        return $this->hasMany(Accommodation::class);
    }

    /** 当前在住人数 */
    public function livingCount(): int
    {
        return $this->accommodations()->where('status', 'living')->count();
    }

    public function isFull(): bool
    {
        return $this->livingCount() >= (int) $this->beds;
    }
}
