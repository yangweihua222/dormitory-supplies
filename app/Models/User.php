<?php

namespace App\Models;

use App\Enums\Identity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'username',
        'employee_no',
        'phone',
        'identity',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'identity' => Identity::class,
            'password' => 'hashed',
        ];
    }

    /** 该用户被勾选的菜单 */
    public function menus(): BelongsToMany
    {
        return $this->belongsToMany(Menu::class, 'user_menu_permissions')
            ->withTimestamps();
    }

    public function isAdmin(): bool
    {
        return $this->identity === Identity::Admin;
    }

    /** 是否拥有某个菜单 key 的权限（以勾选为准——需求 §2.2 身份不直接决定可见性） */
    public function hasMenu(string $key): bool
    {
        return $this->menus()->where('key', $key)->exists();
    }

    /** 已授权菜单 key 列表 */
    public function menuKeys(): array
    {
        return $this->menus()->pluck('key')->all();
    }

    /**
     * 报修「维修情况」的可改人员名单（需求 §6 / 原型 forms.js 权限限制）。
     * 这些人是维修负责人：只能改「维修情况」，其余字段锁定，且不能新增报修。
     */
    public const REPAIR_FOLLOWERS = ['徐长东'];

    /** 能否修改报修的「维修情况」：管理员 + 维修负责人 */
    public function canEditRepairStatus(): bool
    {
        return $this->isAdmin() || in_array($this->username, self::REPAIR_FOLLOWERS, true);
    }

    /** 是否维修负责人（锁定除「维修情况」外的字段、无新增报修权限） */
    public function isRepairFollower(): bool
    {
        return ! $this->isAdmin() && in_array($this->username, self::REPAIR_FOLLOWERS, true);
    }

    /**
     * 维修待办的固定接收人（需求 §6）：维修负责人 + 全部管理员。
     * 与报修单上所选的「跟修人」无关——跟修人只作记录/分工用，不承担待办。
     */
    public static function repairTodoRecipients()
    {
        return static::query()
            ->whereIn('username', self::REPAIR_FOLLOWERS)
            ->orWhere('identity', Identity::Admin->value)
            ->pluck('id');
    }

    /** 能否看见物资类系统提醒（报废提醒 / 数量不符）——需求 §1.1：管理员与徐长东可见 */
    public function canSeeMaterialAlerts(): bool
    {
        return in_array($this->username, self::REPAIR_FOLLOWERS, true);
    }
}
