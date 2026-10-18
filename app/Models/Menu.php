<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Menu extends Model
{
    protected $fillable = ['key', 'label', 'parent_id', 'icon', 'sort'];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_menu_permissions')
            ->withTimestamps();
    }
}
