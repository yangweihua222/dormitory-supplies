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
 * 签到/签退全情景（需求 §4 / 技术方案 §四）：
 * 无排班、请假、正常、迟到计算、重复签到、签退校验、早退计算、跨天值班。
 */
class AttendanceSignInTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** 班次默认 08:00-17:00，today 定格在 10:00 */
    private function prepare(string $now = '2026-09-20 10:00:00', array $shiftAttrs = []): array
    {
        Carbon::setTestNow($now);
        $user = $this->staffUser();
        $shift = Shift::factory()->create(array_merge([
            'name' => '早班',
            'check_in_time' => '08:00:00',
            'check_out_time' => '17:00:00',
        ], $shiftAttrs));

        return [$user, $shift];
    }

    private function signIn(User $user, array $overrides = [])
    {
        return $this->actingAs($user)->postJson('/api/attendance/sign-in', $overrides);
    }

    private function signOut(User $user, array $overrides = [])
    {
        return $this->actingAs($user)->postJson('/api/attendance/sign-out', array_merge([
            'returned_cards' => 0,
            'returned_keys' => 0,
            'is_confirmed' => true,
        ], $overrides));
    }

    // ---------- 无排班 ----------

    public function test_sign_in_without_schedule_is_rejected(): void
    {
        $user = $this->staffUser(); // 无任何排班

        $this->signIn($user)
            ->assertOk()
            ->assertJsonPath('code', 4002)
            ->assertJsonPath('message', '今日无排班，无需签到');

        $this->assertDatabaseCount('attendance_records', 0);
    }

    public function test_sign_out_without_schedule_is_rejected(): void
    {
        $user = $this->staffUser();

        $this->signOut($user)
            ->assertOk()
            ->assertJsonPath('code', 4004)
            ->assertJsonPath('message', '今日无签到记录，无法签退');
    }

    public function test_today_endpoint_reports_no_schedule(): void
    {
        $user = $this->staffUser();

        $this->actingAs($user)->getJson('/api/attendance/today')
            ->assertOk()
            ->assertJsonPath('data.status', '今日无排班')
            ->assertJsonPath('data.leave', false);
    }

    // ---------- 请假 ----------

    public function test_sign_in_on_leave_schedule_is_rejected(): void
    {
        [$user, $shift] = $this->prepare();
        Schedule::factory()->leave()->create(['user_id' => $user->id, 'shift_id' => $shift->id]);

        $this->signIn($user)
            ->assertOk()
            ->assertJsonPath('code', 4007)
            ->assertJsonPath('message', '今日请假中，无需签到');

        $this->assertDatabaseCount('attendance_records', 0);
    }

    public function test_today_endpoint_reports_leave(): void
    {
        [$user, $shift] = $this->prepare();
        Schedule::factory()->leave()->create(['user_id' => $user->id, 'shift_id' => $shift->id]);

        $this->actingAs($user)->getJson('/api/attendance/today')
            ->assertOk()
            ->assertJsonPath('data.status', '请假')
            ->assertJsonPath('data.leave', true)
            ->assertJsonPath('data.shift_name', '早班');
    }

    // ---------- 正常签到 / 迟到 ----------

    public function test_normal_sign_in(): void
    {
        [$user, $shift] = $this->prepare(now: '2026-09-20 07:30:00');
        Schedule::factory()->create(['user_id' => $user->id, 'shift_id' => $shift->id]);

        $this->signIn($user)
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('message', '签到成功')
            ->assertJsonPath('data.late_minutes', 0);

        $this->assertDatabaseHas('attendance_records', [
            'user_id' => $user->id,
            'shift_id' => $shift->id,
            'late_minutes' => 0,
            'check_out_at' => null,
        ]);
    }

    public function test_late_sign_in_calculates_minutes(): void
    {
        // 计划 08:00，实际 08:05 → 迟到 5 分钟
        [$user, $shift] = $this->prepare(now: '2026-09-20 08:05:00');
        Schedule::factory()->create(['user_id' => $user->id, 'shift_id' => $shift->id]);

        $this->signIn($user)
            ->assertOk()
            ->assertJsonPath('data.late_minutes', 5);

        $this->assertDatabaseHas('attendance_records', ['late_minutes' => 5]);
    }

    public function test_duplicate_sign_in_is_rejected(): void
    {
        [$user, $shift] = $this->prepare();
        Schedule::factory()->create(['user_id' => $user->id, 'shift_id' => $shift->id]);
        $this->signIn($user);

        $this->signIn($user)
            ->assertOk()
            ->assertJsonPath('code', 4001)
            ->assertJsonPath('message', '今日已签到，无需重复签到');

        $this->assertDatabaseCount('attendance_records', 1);
    }

    public function test_today_endpoint_after_sign_in(): void
    {
        [$user, $shift] = $this->prepare();
        Schedule::factory()->create(['user_id' => $user->id, 'shift_id' => $shift->id]);
        $this->signIn($user);

        $this->actingAs($user)->getJson('/api/attendance/today')
            ->assertOk()
            ->assertJsonPath('data.status', '已签到')
            ->assertJsonPath('data.plan_check_in', '08:00:00');
    }

    // ---------- 签退 ----------

    public function test_sign_out_requires_confirmation(): void
    {
        [$user, $shift] = $this->prepare();
        Schedule::factory()->create(['user_id' => $user->id, 'shift_id' => $shift->id]);
        $this->signIn($user);

        $this->signOut($user, ['is_confirmed' => false])
            ->assertOk()
            ->assertJsonPath('code', 4003)
            ->assertJsonPath('message', '请先确认物品交接核对');

        // 未确认的签退不落库
        $this->assertDatabaseHas('attendance_records', ['check_out_at' => null]);
    }

    public function test_sign_out_calculates_early_leave(): void
    {
        // 计划签退 17:00，实际 16:00 → 早退 60 分钟
        [$user, $shift] = $this->prepare(now: '2026-09-20 16:00:00');
        Schedule::factory()->create(['user_id' => $user->id, 'shift_id' => $shift->id]);
        $this->signIn($user);

        $this->signOut($user, ['returned_cards' => 2, 'returned_keys' => 1])
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('data.early_leave_minutes', 60);

        $this->assertDatabaseHas('attendance_records', [
            'returned_cards' => 2,
            'returned_keys' => 1,
            'is_confirmed' => true,
            'early_leave_minutes' => 60,
        ]);
    }

    public function test_duplicate_sign_out_is_rejected(): void
    {
        [$user, $shift] = $this->prepare();
        Schedule::factory()->create(['user_id' => $user->id, 'shift_id' => $shift->id]);
        $this->signIn($user);
        $this->signOut($user);

        $this->signOut($user)
            ->assertOk()
            ->assertJsonPath('code', 4006)
            ->assertJsonPath('message', '今日已签退，无需重复签退');

        $this->actingAs($user)->getJson('/api/attendance/today')
            ->assertOk()
            ->assertJsonPath('data.status', '已签退');
    }

    public function test_sign_out_without_record_is_rejected(): void
    {
        [$user, $shift] = $this->prepare();
        Schedule::factory()->create(['user_id' => $user->id, 'shift_id' => $shift->id]);

        $this->signOut($user)
            ->assertOk()
            ->assertJsonPath('code', 4004);
    }

    // ---------- 跨天值班 ----------

    public function test_cross_day_duty_shift_plan_check_out_marks_next_day(): void
    {
        // 值班 22:00-次日 08:00
        [$user] = $this->prepare(now: '2026-09-20 21:30:00', shiftAttrs: [
            'name' => '值班',
            'check_in_time' => '22:00:00',
            'check_out_time' => '08:00:00',
            'type' => 'duty',
        ]);
        $shift = Shift::where('name', '值班')->first();
        Schedule::factory()->create(['user_id' => $user->id, 'shift_id' => $shift->id]);

        // 工作台计划签退显示「次日」
        $this->actingAs($user)->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('data.sign_info.plan_check_out', '次日08:00:00');

        // 当晚 21:30 签到（未到计划签到时间，不算迟到）
        $this->signIn($user)->assertOk()->assertJsonPath('code', 0)
            ->assertJsonPath('data.late_minutes', 0);

        // 次日凌晨签退：计划 09-21 08:00，实际 09-21 07:00 → 早退 60 分钟
        Carbon::setTestNow('2026-09-21 07:00:00');
        $this->signOut($user)
            ->assertOk()
            ->assertJsonPath('data.early_leave_minutes', 60);
    }

    // ---------- 过零点补签（夜班跨天） ----------

    public function test_night_shift_sign_in_after_midnight_counts_late(): void
    {
        // 值班 09-20 22:00 - 09-21 08:00，忘了签到，09-21 00:30 才想起来
        [$user, $shift] = $this->prepare(now: '2026-09-21 00:30:00', shiftAttrs: [
            'name' => '值班',
            'check_in_time' => '22:00:00',
            'check_out_time' => '08:00:00',
            'type' => 'duty',
        ]);
        Schedule::factory()->create([
            'user_id' => $user->id,
            'shift_id' => $shift->id,
            'date' => '2026-09-20',
        ]);

        $this->signIn($user)
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('data.late_minutes', 150);

        // 记录归属排班当天 09-20，计划时间为 22:00 / 次日 08:00
        $record = AttendanceRecord::where('user_id', $user->id)->first();
        $this->assertSame('2026-09-20 22:00:00', $record->plan_check_in_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-21 08:00:00', $record->plan_check_out_at->format('Y-m-d H:i:s'));

        // 跨零点后今日状态仍显示夜班信息（已签到、计划时间带昨日/次日标注）
        $this->actingAs($user)->getJson('/api/attendance/today')
            ->assertOk()
            ->assertJsonPath('data.status', '已签到')
            ->assertJsonPath('data.plan_check_in', '昨日22:00:00')
            ->assertJsonPath('data.plan_check_out', '次日08:00:00');

        // 工作台同样显示
        $this->actingAs($user)->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('data.sign_info.status', '已签到')
            ->assertJsonPath('data.sign_info.plan_check_in', '昨日22:00:00');

        // 07:00 签退 → 早退 60 分钟
        Carbon::setTestNow('2026-09-21 07:00:00');
        $this->signOut($user)
            ->assertOk()
            ->assertJsonPath('data.early_leave_minutes', 60);
    }

    public function test_night_shift_not_signed_in_yet_shows_pending_after_midnight(): void
    {
        // 夜班过零点还没签到：今日状态显示「未签到」（可补签），而非「今日无排班」
        [$user, $shift] = $this->prepare(now: '2026-09-21 00:30:00', shiftAttrs: [
            'name' => '值班',
            'check_in_time' => '22:00:00',
            'check_out_time' => '08:00:00',
            'type' => 'duty',
        ]);
        Schedule::factory()->create([
            'user_id' => $user->id,
            'shift_id' => $shift->id,
            'date' => '2026-09-20',
        ]);

        $this->actingAs($user)->getJson('/api/attendance/today')
            ->assertOk()
            ->assertJsonPath('data.status', '未签到')
            ->assertJsonPath('data.shift_name', '值班')
            ->assertJsonPath('data.plan_check_in', '昨日22:00:00');

        $this->actingAs($user)->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('data.sign_info.status', '未签到');
    }

    public function test_night_shift_sign_in_rejected_after_shift_ended(): void
    {
        // 09:00 才想起来，夜班 08:00 已结束 → 不允许补签（记录显示缺卡）
        [$user, $shift] = $this->prepare(now: '2026-09-21 09:00:00', shiftAttrs: [
            'name' => '值班',
            'check_in_time' => '22:00:00',
            'check_out_time' => '08:00:00',
            'type' => 'duty',
        ]);
        Schedule::factory()->create([
            'user_id' => $user->id,
            'shift_id' => $shift->id,
            'date' => '2026-09-20',
        ]);

        $this->signIn($user)
            ->assertOk()
            ->assertJsonPath('code', 4002)
            ->assertJsonPath('message', '今日无排班，无需签到');

        $this->assertDatabaseCount('attendance_records', 0);

        // 今日状态回到「今日无排班」
        $this->actingAs($user)->getJson('/api/attendance/today')
            ->assertOk()
            ->assertJsonPath('data.status', '今日无排班');
    }

    public function test_yesterday_leave_night_shift_does_not_fall_back(): void
    {
        // 昨天请假（夜班排班置为请假）：跨零点后不回看、不能补签
        [$user, $shift] = $this->prepare(now: '2026-09-21 00:30:00', shiftAttrs: [
            'name' => '值班',
            'check_in_time' => '22:00:00',
            'check_out_time' => '08:00:00',
            'type' => 'duty',
        ]);
        Schedule::factory()->leave()->create([
            'user_id' => $user->id,
            'shift_id' => $shift->id,
            'date' => '2026-09-20',
        ]);

        $this->signIn($user)
            ->assertOk()
            ->assertJsonPath('code', 4002);

        $this->assertDatabaseCount('attendance_records', 0);
    }

    public function test_yesterday_normal_shift_does_not_fall_back(): void
    {
        // 昨天是普通班（08:00-17:00，非跨天）：今天无排班就是无排班
        [$user, $shift] = $this->prepare(now: '2026-09-21 10:00:00');
        Schedule::factory()->create([
            'user_id' => $user->id,
            'shift_id' => $shift->id,
            'date' => '2026-09-20',
        ]);

        $this->signIn($user)
            ->assertOk()
            ->assertJsonPath('code', 4002);
    }

    // ---------- 签到记录归属 ----------

    public function test_attendance_record_keeps_plan_times(): void
    {
        [$user, $shift] = $this->prepare();
        Schedule::factory()->create(['user_id' => $user->id, 'shift_id' => $shift->id]);
        $this->signIn($user);

        $record = AttendanceRecord::where('user_id', $user->id)->first();

        $this->assertSame('2026-09-20 08:00:00', $record->plan_check_in_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-20 17:00:00', $record->plan_check_out_at->format('Y-m-d H:i:s'));
    }
}
