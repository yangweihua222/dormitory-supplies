<?php

namespace Tests\Feature;

use App\Models\Leave;
use App\Models\LeaveBalance;
use App\Models\Schedule;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 请假审批联动（需求 §5 / 跨模块联动）：
 * 审批通过 → 区间内排班置「请假」；年假扣减；后补排班自动反向联动。
 */
class LeaveApprovalTest extends TestCase
{
    use RefreshDatabase;

    private function pendingLeave(User $user, array $overrides = []): Leave
    {
        return Leave::create(array_merge([
            'user_id' => $user->id,
            'applied_at' => now(),
            'type' => 'personal',
            'reason' => '家中有事',
            'start_date' => '2026-09-20',
            'end_date' => '2026-09-21',
            'approve_status' => 'pending',
        ], $overrides));
    }

    public function test_non_admin_cannot_approve(): void
    {
        // 有「请假列表」菜单但不是管理员
        $staff = $this->grantMenus($this->staffUser(), 'leave');
        $leave = $this->pendingLeave($staff);

        $this->actingAs($staff)->postJson("/api/leaves/{$leave->id}/approve")
            ->assertOk()
            ->assertJsonPath('code', 3003)
            ->assertJsonPath('message', '只有管理员可以审批');
    }

    public function test_approve_marks_schedules_in_range_as_leave(): void
    {
        $staff = $this->staffUser();
        $shift = Shift::factory()->create();
        // 区间内 + 区间外各一条排班
        $inRange = Schedule::factory()->create(['user_id' => $staff->id, 'shift_id' => $shift->id, 'date' => '2026-09-20']);
        $outRange = Schedule::factory()->create(['user_id' => $staff->id, 'shift_id' => $shift->id, 'date' => '2026-09-25']);
        $leave = $this->pendingLeave($staff);

        $res = $this->actingAs($this->adminUser())->postJson("/api/leaves/{$leave->id}/approve");

        $res->assertOk()->assertJsonPath('code', 0);

        $this->assertSame('leave', $inRange->refresh()->status->value);
        $this->assertSame($leave->id, $inRange->refresh()->leave_id);
        $this->assertSame('normal', $outRange->refresh()->status->value);
        $this->assertNull($outRange->refresh()->leave_id);
    }

    public function test_double_approve_is_rejected(): void
    {
        $staff = $this->staffUser();
        $leave = $this->pendingLeave($staff);

        $this->actingAs($this->adminUser())->postJson("/api/leaves/{$leave->id}/approve")->assertOk();
        $this->actingAs($this->adminUser())->postJson("/api/leaves/{$leave->id}/approve")
            ->assertOk()
            ->assertJsonPath('code', 3002)
            ->assertJsonPath('message', '该申请已审批，请勿重复操作');
    }

    public function test_annual_leave_deducts_balance(): void
    {
        $staff = $this->staffUser();
        LeaveBalance::create([
            'user_id' => $staff->id,
            'total_days' => 5,
            'remain_days' => 5,
        ]);
        $leave = $this->pendingLeave($staff, ['type' => 'annual']);

        $this->actingAs($this->adminUser())->postJson("/api/leaves/{$leave->id}/approve")
            ->assertOk()
            ->assertJsonPath('code', 0);

        // 年假 2 天：5 → 3
        $this->assertEquals(3.0, (float) LeaveBalance::where('user_id', $staff->id)->value('remain_days'));
    }

    public function test_schedule_created_after_approval_auto_syncs_leave(): void
    {
        // 先审批请假，再排班 → booted 钩子反向补齐
        $staff = $this->staffUser();
        $leave = $this->pendingLeave($staff);
        $this->actingAs($this->adminUser())->postJson("/api/leaves/{$leave->id}/approve");

        $shift = Shift::factory()->create();
        $schedule = Schedule::factory()->create(['user_id' => $staff->id, 'shift_id' => $shift->id, 'date' => '2026-09-21']);

        $this->assertSame('leave', $schedule->refresh()->status->value);
        $this->assertSame($leave->id, $schedule->refresh()->leave_id);
    }
}
