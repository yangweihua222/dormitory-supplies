<?php

namespace App\Models;

use App\Enums\ApproveStatus;
use App\Enums\ScheduleStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Schedule extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['user_id', 'date', 'shift_id', 'status', 'leave_id'];

    protected $casts = [
        'date' => 'date',
        'status' => ScheduleStatus::class,
    ];

    /**
     * 反向联动：排班若在「已批准的请假」区间内，自动置为请假。
     * 覆盖「先审批请假、之后才排班 / 改期排班」的场景（审批时只能联动已存在的排班）。
     */
    protected static function booted(): void
    {
        $syncLeave = function (Schedule $schedule) {
            // 撤销联动时（leave_id 由有值改为 null）不回填，避免与 revert 打架
            if ($schedule->exists
                && $schedule->isDirty('leave_id')
                && $schedule->getOriginal('leave_id') !== null
                && $schedule->leave_id === null) {
                return;
            }

            // 已明确归属某条请假的，或已是请假状态的，不覆盖
            if ($schedule->leave_id || $schedule->status === ScheduleStatus::LEAVE) {
                return;
            }
            if (! $schedule->user_id || ! $schedule->date) {
                return;
            }

            $leave = Leave::where('user_id', $schedule->user_id)
                ->where('approve_status', ApproveStatus::APPROVED)
                ->where('start_date', '<=', $schedule->date->format('Y-m-d'))
                ->where('end_date', '>=', $schedule->date->format('Y-m-d'))
                ->orderByDesc('id')
                ->first();

            if ($leave) {
                $schedule->status = ScheduleStatus::LEAVE;
                $schedule->leave_id = $leave->id;
            }
        };

        static::creating($syncLeave);
        static::updating($syncLeave);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 解析用户在某天的「可签到排班」：
     * 1) 仍在进行中的昨日跨天夜班（计划签退落在今天且尚未结束、未完整签退）——
     *    覆盖「值班过零点才想起来签到 / 夜班跨零点仍在班中」的场景，优先于当天排班；
     * 2) 否则取当天的排班。
     * 跨天判定与班次类型无关（值班/正常班按时间判定），普通班（签退晚于签到）不参与回看。
     */
    public static function findSignableSchedule(int $userId, Carbon $today): ?self
    {
        $yesterday = static::with('shift')
            ->where('user_id', $userId)
            ->where('date', $today->copy()->subDay())
            ->first();

        if ($yesterday && $yesterday->shift
            && $yesterday->status !== ScheduleStatus::LEAVE
            && ! $yesterday->leave_id
            && $yesterday->shift->check_out_time->format('H:i:s') <= $yesterday->shift->check_in_time->format('H:i:s')) {
            // 计划签退（排班日次日）尚未过去 → 班次仍在进行中
            $planCheckOut = $yesterday->date->copy()->setTimeFrom($yesterday->shift->check_out_time)->addDay();

            // 已完整签退（签了且退了）的夜班不再拦截当天签到
            $fullyDone = AttendanceRecord::query()
                ->where('user_id', $userId)
                ->where('date', $yesterday->date)
                ->where('shift_id', $yesterday->shift_id)
                ->whereNotNull('check_in_at')
                ->whereNotNull('check_out_at')
                ->exists();

            if ($planCheckOut->gt(Carbon::now()) && ! $fullyDone) {
                return $yesterday;
            }
        }

        return static::with('shift')
            ->where('user_id', $userId)
            ->where('date', $today)
            ->first();
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }

    public function leave()
    {
        return $this->belongsTo(Leave::class);
    }
}
