<?php

namespace App\Models;

use App\Enums\CheckResult;
use App\Enums\EmergencyCategory;
use App\Enums\SafetyCategory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 安全检查 / 安全事件（需求 §7.1 前 7 个子类）
 * 一张表承载，category 决定用哪套字段：
 * - check（宿舍消防安全检查 / 公寓消防安全隐患排查）：逐房间用电安全检查——
 *   房间号、检查人员、外来排查人员、6 项用电检查、检查结果、整改状态、备注（对齐原型）
 * - event（公共秩序 / 财产安全 / 人身安全 / 环境卫生安全）：事件描述、时间、地点、人物、经过、图片
 * - emergency（突发类安全事件）：事件类字段 + 事件类别
 */
class SafetyCheck extends Model
{
    use HasFactory, SoftDeletes;

    /** 整改状态 */
    public const RECTIFY_DONE = 'done';

    public const RECTIFY_PENDING = 'pending';

    public const RECTIFY_LABELS = [
        self::RECTIFY_DONE => '已完成',
        self::RECTIFY_PENDING => '未完成',
    ];

    protected $fillable = [
        'category', 'check_date', 'room_id',
        'private_wiring', 'strip_overload', 'high_wattage', 'power_off', 'trash_at_door', 'smoke_blocked',
        'rectify', 'external_inspector',
        'result', 'remark', 'inspector_ids',
        'event_desc', 'event_time', 'place', 'person', 'process', 'images',
        'event_category', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'category' => SafetyCategory::class,
            'result' => CheckResult::class,
            'event_category' => EmergencyCategory::class,
            'check_date' => 'date:Y-m-d',
            'event_time' => 'datetime',
            'inspector_ids' => 'array',
            'images' => 'array',
            'private_wiring' => 'boolean',
            'strip_overload' => 'boolean',
            'high_wattage' => 'boolean',
            'power_off' => 'boolean',
            'trash_at_door' => 'boolean',
            'smoke_blocked' => 'boolean',
        ];
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    /** 整改状态中文标签 */
    public function rectifyLabel(): ?string
    {
        return $this->rectify ? (self::RECTIFY_LABELS[$this->rectify] ?? $this->rectify) : null;
    }

    /** 检查人员姓名列表（按传入顺序） */
    public function inspectorNames(): array
    {
        $ids = $this->inspector_ids ?? [];
        if (! $ids) {
            return [];
        }

        return User::whereIn('id', $ids)
            ->get(['id', 'username'])
            ->sortBy(fn ($u) => array_search($u->id, $ids))
            ->pluck('username')
            ->all();
    }
}
