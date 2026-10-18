<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\Schedule;
use App\Models\Shift;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 连续多日签到/签退互不干扰（2026-09-20 用户提出的场景矩阵）：
 * 正常班连续 / 值班连续 / 正常班(跨天班次) / 值班跨天 / 混合交替 /
 * 漏签退遗留 / 夜班班中优先级 / 已签退夜班不拦截。
 */
class AttendanceMultiDayTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function makeShift(string $name, string $in, string $out, string $type = 'normal'): Shift
    {
        return Shift::factory()->create([
            'name' => $name,
            'check_in_time' => $in,
            'check_out_time' => $out,
            'type' => $type,
        ]);
    }

    private function schedule(User $user, Shift $shift, string $date): Schedule
    {
        return Schedule::factory()->create([
            'user_id' => $user->id,
            'shift_id' => $shift->id,
            'date' => $date,
        ]);
    }

    private function signIn(User $user)
    {
        return $this->actingAs($user)->postJson('/api/attendance/sign-in');
    }

    private function signOut(User $user)
    {
        return $this->actingAs($user)->postJson('/api/attendance/sign-out', [
            'is_confirmed' => true,
            'returned_cards' => 0,
            'returned_keys' => 0,
        ]);
    }

    private function recordOf(string $date): ?AttendanceRecord
    {
        return AttendanceRecord::whereDate('date', $date)->first();
    }

    // ---------- 正常班（08:00-18:00）连续两天 ----------

    public function test_consecutive_normal_days_are_independent(): void
    {
        $user = $this->staffUser();
        $normal = $this->makeShift('白班', '08:00:00', '18:00:00');

        // 第一天：08:05 签到（迟到5）→ 18:00 签退
        Carbon::setTestNow('2026-09-20 08:05:00');
        $this->schedule($user, $normal, '2026-09-20');
        $this->signIn($user)->assertOk()->assertJsonPath('data.late_minutes', 5);
        Carbon::setTestNow('2026-09-20 18:00:00');
        $this->signOut($user)->assertOk();

        // 第二天：08:00 签到 → 17:00 签退（早退60），与第一天互不影响
        Carbon::setTestNow('2026-09-21 08:00:00');
        $this->schedule($user, $normal, '2026-09-21');
        $this->signIn($user)->assertOk()->assertJsonPath('data.late_minutes', 0);
        Carbon::setTestNow('2026-09-21 17:00:00');
        $this->signOut($user)->assertOk()->assertJsonPath('data.early_leave_minutes', 60);

        // 两天各一条完整、数据正确的记录
        $this->assertSame(2, AttendanceRecord::where('user_id', $user->id)->count());
        $day1 = $this->recordOf('2026-09-20');
        $day2 = $this->recordOf('2026-09-21');
        $this->assertNotNull($day1->check_out_at);
        $this->assertNotNull($day2->check_out_at);
        $this->assertEquals(5, $day1->late_minutes);
        $this->assertEquals(60, $day2->early_leave_minutes);
    }

    public function test_normal_shift_cannot_sign_in_after_midnight(): void
    {
        // 正常班 8-18 下班后过了零点：不能补签（不是跨天班次）
        $user = $this->staffUser();
        $normal = $this->makeShift('白班', '08:00:00', '18:00:00');

        Carbon::setTestNow('2026-09-21 00:30:00');
        $this->schedule($user, $normal, '2026-09-20');

        $this->signIn($user)
            ->assertOk()
            ->assertJsonPath('code', 4002)
            ->assertJsonPath('message', '今日无排班，无需签到');

        $this->assertDatabaseCount('attendance_records', 0);
    }

    // ---------- 值班（22:00-次日08:00）连续两晚 ----------

    public function test_consecutive_duty_nights_are_independent(): void
    {
        $user = $this->staffUser();
        $duty = $this->makeShift('值班', '22:00:00', '08:00:00', 'duty');

        // 第一晚：22:00 签到 → 次日 07:30 签退
        Carbon::setTestNow('2026-09-20 22:00:00');
        $this->schedule($user, $duty, '2026-09-20');
        $this->signIn($user)->assertOk()->assertJsonPath('data.late_minutes', 0);
        Carbon::setTestNow('2026-09-21 07:30:00');
        $this->signOut($user)->assertOk();

        // 第二晚：22:00 签到 → 第三天 07:30 签退，两晚互不影响
        Carbon::setTestNow('2026-09-21 22:00:00');
        $this->schedule($user, $duty, '2026-09-21');
        $this->signIn($user)->assertOk()->assertJsonPath('data.late_minutes', 0);
        Carbon::setTestNow('2026-09-22 07:30:00');
        $this->signOut($user)->assertOk()->assertJsonPath('data.early_leave_minutes', 30);

        $this->assertSame(2, AttendanceRecord::where('user_id', $user->id)->count());
        $n1 = $this->recordOf('2026-09-20');
        $n2 = $this->recordOf('2026-09-21');
        $this->assertSame('2026-09-21 08:00:00', $n1->plan_check_out_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-22 08:00:00', $n2->plan_check_out_at->format('Y-m-d H:i:s'));
        $this->assertNotNull($n1->check_out_at);
        $this->assertNotNull($n2->check_out_at);
    }

    // ---------- 正常班类型的跨天班次（20:00-04:00，非值班） ----------

    public function test_cross_day_normal_type_shift_midnight_sign_in(): void
    {
        // 跨天判定按时间而非类型：普通类型的跨天班同样支持过零点补签
        $user = $this->staffUser();
        $night = $this->makeShift('晚班', '20:00:00', '04:00:00');

        Carbon::setTestNow('2026-09-21 01:00:00');
        $this->schedule($user, $night, '2026-09-20');
        $this->signIn($user)
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('data.late_minutes', 300); // 20:00 → 01:00

        Carbon::setTestNow('2026-09-21 03:30:00');
        $this->signOut($user)
            ->assertOk()
            ->assertJsonPath('data.early_leave_minutes', 30); // 计划 04:00

        $record = $this->recordOf('2026-09-20');
        $this->assertSame('2026-09-21 04:00:00', $record->plan_check_out_at->format('Y-m-d H:i:s'));
    }

    // ---------- 混合交替：第一天正常班，第二天值班，第三天正常班 ----------

    public function test_mixed_normal_duty_normal_sequence(): void
    {
        $user = $this->staffUser();
        $normal = $this->makeShift('白班', '08:00:00', '18:00:00');
        $duty = $this->makeShift('值班', '22:00:00', '08:00:00', 'duty');

        // 第一天 正常班
        Carbon::setTestNow('2026-09-20 08:00:00');
        $this->schedule($user, $normal, '2026-09-20');
        $this->signIn($user)->assertOk();
        Carbon::setTestNow('2026-09-20 18:00:00');
        $this->signOut($user)->assertOk();

        // 第二天 值班：当晚 22:00 签到 → 次日 07:30 签退
        Carbon::setTestNow('2026-09-21 22:00:00');
        $this->schedule($user, $duty, '2026-09-21');
        $this->signIn($user)->assertOk();
        Carbon::setTestNow('2026-09-22 07:30:00');
        $this->signOut($user)->assertOk();

        // 第三天 正常班：签到签退不受前两日影响
        Carbon::setTestNow('2026-09-22 08:00:00');
        $this->schedule($user, $normal, '2026-09-22');
        $this->signIn($user)->assertOk()->assertJsonPath('data.late_minutes', 0);
        Carbon::setTestNow('2026-09-22 18:00:00');
        $this->signOut($user)->assertOk();

        // 三天各一条完整记录
        $this->assertSame(3, AttendanceRecord::where('user_id', $user->id)->count());
        foreach (['2026-09-20', '2026-09-21', '2026-09-22'] as $date) {
            $this->assertNotNull($this->recordOf($date)->check_out_at, "{$date} 应已签退");
        }
    }

    // ---------- 夜班跨天 + 次日早班连排（优先级） ----------

    public function test_in_progress_night_shift_takes_priority_over_today_schedule(): void
    {
        // 夜班 22:00-次日08:00 且次日又排了早班：凌晨签到必须命中「进行中的夜班」
        $user = $this->staffUser();
        $duty = $this->makeShift('值班', '22:00:00', '08:00:00', 'duty');
        $morning = $this->makeShift('早班', '08:00:00', '18:00:00');

        $this->schedule($user, $duty, '2026-09-20');
        $this->schedule($user, $morning, '2026-09-21');

        // 凌晨 00:30 签到 → 命中昨晚值班（迟到 150），而非明早的班
        Carbon::setTestNow('2026-09-21 00:30:00');
        $this->signIn($user)
            ->assertOk()
            ->assertJsonPath('data.late_minutes', 150);
        $this->assertNotNull(AttendanceRecord::where('shift_id', $duty->id)->whereDate('date', '2026-09-20')->first());

        // 07:45 夜班签退（早退 15）→ 08:00 早班签到（夜班窗口已关）
        Carbon::setTestNow('2026-09-21 07:45:00');
        $this->signOut($user)->assertOk()->assertJsonPath('data.early_leave_minutes', 15);
        Carbon::setTestNow('2026-09-21 08:00:00');
        $this->signIn($user)->assertOk();
        $this->assertNotNull(AttendanceRecord::where('shift_id', $morning->id)->whereDate('date', '2026-09-21')->first());

        // 18:00 早班签退 → 两条记录各自完整闭合
        Carbon::setTestNow('2026-09-21 18:00:00');
        $this->signOut($user)->assertOk();
        $this->assertSame(2, AttendanceRecord::where('user_id', $user->id)->count());
        $this->assertNotNull($this->recordOf('2026-09-20')->check_out_at);
        $this->assertNotNull($this->recordOf('2026-09-21')->check_out_at);
    }

    public function test_completed_night_shift_does_not_block_midnight_requests(): void
    {
        // 夜班当晚已完整签到+签退：跨零点后不该再提示「已签到」
        $user = $this->staffUser();
        $duty = $this->makeShift('值班', '22:00:00', '08:00:00', 'duty');

        Carbon::setTestNow('2026-09-20 22:00:00');
        $this->schedule($user, $duty, '2026-09-20');
        $this->signIn($user)->assertOk();
        Carbon::setTestNow('2026-09-20 23:00:00');
        $this->signOut($user)->assertOk(); // 提前签退，整段已完成

        // 跨零点后：今天无排班就是无排班
        Carbon::setTestNow('2026-09-21 00:30:00');
        $this->signIn($user)
            ->assertOk()
            ->assertJsonPath('code', 4002)
            ->assertJsonPath('message', '今日无排班，无需签到');

        $this->actingAs($user)->getJson('/api/attendance/today')
            ->assertOk()
            ->assertJsonPath('data.status', '今日无排班');
    }

    // ---------- 漏签退遗留 ----------

    public function test_forgotten_sign_out_does_not_block_next_day(): void
    {
        // 第一天忘了签退（留下开放记录）→ 不影响第二天的签到签退
        $user = $this->staffUser();
        $normal = $this->makeShift('白班', '08:00:00', '18:00:00');

        Carbon::setTestNow('2026-09-20 08:00:00');
        $this->schedule($user, $normal, '2026-09-20');
        $this->signIn($user)->assertOk();
        // （未签退，直接跳到第二天）

        Carbon::setTestNow('2026-09-21 08:00:00');
        $this->schedule($user, $normal, '2026-09-21');
        $this->signIn($user)->assertOk()->assertJsonPath('code', 0);
        Carbon::setTestNow('2026-09-21 18:00:00');
        $this->signOut($user)->assertOk()->assertJsonPath('code', 0);

        // 第二天的签退关的是第二天的记录（最近开放记录），第一天遗留为「未签退」
        $day1 = $this->recordOf('2026-09-20');
        $day2 = $this->recordOf('2026-09-21');
        $this->assertNull($day1->check_out_at);
        $this->assertNotNull($day2->check_out_at);
        $this->assertEquals(0, $day2->early_leave_minutes);
    }

    public function test_forgotten_duty_sign_out_does_not_block_next_duty(): void
    {
        // 第一晚值班只签到忘了签退 → 不影响第二晚值班的签到与次日签退
        $user = $this->staffUser();
        $duty = $this->makeShift('值班', '22:00:00', '08:00:00', 'duty');

        Carbon::setTestNow('2026-09-20 22:00:00');
        $this->schedule($user, $duty, '2026-09-20');
        $this->signIn($user)->assertOk();
        // （未签退）

        Carbon::setTestNow('2026-09-21 22:00:00');
        $this->schedule($user, $duty, '2026-09-21');
        $this->signIn($user)->assertOk()->assertJsonPath('code', 0);
        Carbon::setTestNow('2026-09-22 07:30:00');
        $this->signOut($user)->assertOk()->assertJsonPath('code', 0);

        // 第二晚的记录正常闭合，第一晚遗留「未签退」
        $n1 = $this->recordOf('2026-09-20');
        $n2 = $this->recordOf('2026-09-21');
        $this->assertNull($n1->check_out_at);
        $this->assertNotNull($n2->check_out_at);
    }
}
