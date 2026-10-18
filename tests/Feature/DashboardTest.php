<?php

namespace Tests\Feature;

use App\Models\Schedule;
use App\Models\Shift;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_sign_info_reports_no_schedule(): void
    {
        $user = $this->staffUser();

        $this->actingAs($user)->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('data.sign_info.status', '今日无排班')
            ->assertJsonPath('data.sign_info.shift_name', '—');
    }

    public function test_sign_info_reports_leave(): void
    {
        $user = $this->staffUser();
        $shift = Shift::factory()->create();
        Schedule::factory()->leave()->create(['user_id' => $user->id, 'shift_id' => $shift->id]);

        $this->actingAs($user)->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('data.sign_info.status', '请假');
    }

    public function test_sign_info_reports_signed_out(): void
    {
        Carbon::setTestNow('2026-09-20 12:00:00');
        $user = $this->staffUser();
        $shift = Shift::factory()->create();
        Schedule::factory()->create(['user_id' => $user->id, 'shift_id' => $shift->id]);
        $this->actingAs($user)->postJson('/api/attendance/sign-in');
        $this->actingAs($user)->postJson('/api/attendance/sign-out', [
            'returned_cards' => 0, 'returned_keys' => 0, 'is_confirmed' => true,
        ]);

        $this->actingAs($user)->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('data.sign_info.status', '已签退')
            ->assertJsonPath('data.sign_info.check_in_at', '12:00:00');
    }

    public function test_quick_entries_filtered_by_menu_permission(): void
    {
        // 只勾「考勤统计」的普通员工：快速入口只剩考勤统计
        $user = $this->grantMenus(User::factory()->create(), 'attendance');

        $res = $this->actingAs($user)->getJson('/api/dashboard');

        $res->assertOk();
        $keys = collect($res->json('data.quick_entries'))->pluck('key');
        $this->assertSame(['attendance'], $keys->values()->all());
    }

    public function test_duty_users_lists_today_duty_shift_members(): void
    {
        $dutyShift = Shift::factory()->duty()->create();
        $normalShift = Shift::factory()->create(['name' => '日班']);
        $dutyUser = User::factory()->create();
        $normalUser = User::factory()->create();

        Schedule::factory()->create(['user_id' => $dutyUser->id, 'shift_id' => $dutyShift->id]);
        Schedule::factory()->create(['user_id' => $normalUser->id, 'shift_id' => $normalShift->id]);

        $res = $this->actingAs($normalUser)->getJson('/api/dashboard');

        $names = collect($res->json('data.duty_users'))->pluck('name');
        $this->assertContains($dutyUser->username, $names);
        $this->assertNotContains($normalUser->username, $names);
    }

    public function test_admin_stats_included_only_for_admin(): void
    {
        $admin = $this->adminUser();
        $staff = $this->staffUser();

        $this->actingAs($admin)->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonStructure(['data' => ['stats' => ['handover_total', 'handover_done', 'handover_rate', 'signed_count']]]);

        $this->actingAs($staff)->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('data.stats', null);
    }
}
