<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentAppeal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 考核管理（需求 10）：月度考核 CRUD 与数据范围、重复创建拦截、后端算分；
 * 考核申诉流转；同行评议算分与改删权限；工作管理月度考核统计与两个图表导出。
 */
class AssessmentTest extends TestCase
{
    use RefreshDatabase;

    private function items(array $ded = [0, 0, 0, 0, 0], array $bon = [0, 0, 0, 0, 0]): array
    {
        return array_map(fn ($i) => ['ded' => $ded[$i], 'bon' => $bon[$i], 'note' => ''], array_keys($ded));
    }

    // ---------- 月度考核 ----------

    public function test_admin_create_assessment_computes_total_and_grade(): void
    {
        $admin = $this->adminUser();
        $staff = User::factory()->create();

        // 扣分 6+5+6+3+0=20，加分 0 → total=80 → 合格
        $res = $this->actingAs($admin)->postJson('/api/assessments', [
            'user_id' => $staff->id,
            'month' => '2026-08',
            'assess_date' => '2026-09-01',
            'items' => $this->items([6, 5, 6, 3, 0]),
        ]);

        $res->assertOk()->assertJsonPath('code', 0)
            ->assertJsonPath('data.total', 80)
            ->assertJsonPath('data.grade', '合格')
            ->assertJsonPath('data.name', $staff->username)
            ->assertJsonPath('data.post', '前台值班人员');

        $this->assertDatabaseHas('assessments', ['user_id' => $staff->id, 'month' => '2026-08', 'grade' => '合格']);
    }

    public function test_bonus_can_push_total_above_85(): void
    {
        $admin = $this->adminUser();
        $staff = User::factory()->create();

        // 基础 100，加分 5 → 105 → 优秀
        $this->actingAs($admin)->postJson('/api/assessments', [
            'user_id' => $staff->id,
            'month' => '2026-08',
            'assess_date' => '2026-09-01',
            'items' => $this->items([0, 0, 0, 0, 0], [0, 0, 0, 5, 0]),
        ])->assertJsonPath('data.total', 105)
            ->assertJsonPath('data.grade', '优秀');
    }

    public function test_duplicate_user_month_is_rejected(): void
    {
        $admin = $this->adminUser();
        $staff = User::factory()->create();
        $payload = [
            'user_id' => $staff->id,
            'month' => '2026-08',
            'assess_date' => '2026-09-01',
            'items' => $this->items(),
        ];
        $this->actingAs($admin)->postJson('/api/assessments', $payload)->assertJsonPath('code', 0);

        $this->actingAs($admin)->postJson('/api/assessments', $payload)
            ->assertJsonPath('code', 4001);
    }

    public function test_staff_only_sees_own_records_and_cannot_write(): void
    {
        $admin = $this->adminUser();
        $staffA = User::factory()->create();
        $staffB = User::factory()->create();
        $this->grantMenus($staffA, ['assess']);
        $this->grantMenus($staffB, ['assess']);

        foreach ([$staffA, $staffB] as $u) {
            Assessment::create([
                'user_id' => $u->id, 'month' => '2026-08', 'assess_date' => '2026-09-01',
                'items' => $this->items(), 'total' => 100, 'grade' => '优秀',
            ]);
        }

        // A 只能看到自己的 1 条
        $res = $this->actingAs($staffA)->getJson('/api/assessments');
        $res->assertOk();
        $this->assertCount(1, $res->json('data.list'));
        $this->assertSame($staffA->username, $res->json('data.list.0.name'));

        // A 创建/修改/删除均被拒
        $this->actingAs($staffA)->postJson('/api/assessments', [
            'user_id' => $staffA->id, 'month' => '2026-09', 'assess_date' => '2026-10-01',
            'items' => $this->items(),
        ])->assertForbidden();
        $row = Assessment::where('user_id', $staffB->id)->first();
        $this->actingAs($staffA)->putJson("/api/assessments/{$row->id}", [
            'assess_date' => '2026-09-02', 'items' => $this->items(),
        ])->assertForbidden();
        $this->actingAs($staffA)->deleteJson("/api/assessments/{$row->id}")->assertForbidden();
    }

    public function test_update_recomputes_total_and_grade(): void
    {
        $admin = $this->adminUser();
        $staff = User::factory()->create();
        $row = Assessment::create([
            'user_id' => $staff->id, 'month' => '2026-08', 'assess_date' => '2026-09-01',
            'items' => $this->items(), 'total' => 100, 'grade' => '优秀',
        ]);

        // 全维度扣到 52 → 不合格
        $this->actingAs($admin)->putJson("/api/assessments/{$row->id}", [
            'assess_date' => '2026-09-01',
            'items' => $this->items([12, 10, 18, 5, 3]),
        ])->assertJsonPath('data.total', 52)
            ->assertJsonPath('data.grade', '不合格');
    }

    // ---------- 考核申诉 ----------

    public function test_appeal_admin_create_pending_then_done_after_result(): void
    {
        $admin = $this->adminUser();
        $staff = User::factory()->create();

        $res = $this->actingAs($admin)->postJson('/api/assessment-appeals', [
            'user_id' => $staff->id,
            'month' => '2026-08',
            'matter' => '对安全管理项扣分有异议',
            'appeal_date' => '2026-08-28 10:12',
        ]);
        $res->assertJsonPath('data.status', 'pending')->assertJsonPath('data.status_label', '申诉中');

        $id = $res->json('data.id');
        $this->actingAs($admin)->putJson("/api/assessment-appeals/{$id}", [
            'user_id' => $staff->id,
            'month' => '2026-08',
            'matter' => '对安全管理项扣分有异议',
            'appeal_date' => '2026-08-28 10:12',
            'result' => '经核查维持原认定，扣分下调 3 分',
            'rectify' => '加强夜间通道巡查',
        ])->assertJsonPath('data.status', 'done')->assertJsonPath('data.status_label', '已完成');
    }

    public function test_appeal_staff_only_sees_own(): void
    {
        $admin = $this->adminUser();
        $staffA = User::factory()->create();
        $staffB = User::factory()->create();
        $this->grantMenus($staffA, ['assess-appeal']);

        foreach ([$staffA, $staffB] as $u) {
            AssessmentAppeal::create([
                'user_id' => $u->id, 'month' => '2026-08', 'matter' => '测试申诉',
                'appeal_date' => now(),
            ]);
        }

        $res = $this->actingAs($staffA)->getJson('/api/assessment-appeals');
        $this->assertCount(1, $res->json('data.list'));
        $this->actingAs($staffA)->postJson('/api/assessment-appeals', [
            'user_id' => $staffA->id, 'month' => '2026-08', 'matter' => 'x', 'appeal_date' => '2026-08-28 10:00',
        ])->assertForbidden();
    }

    // ---------- 同行评议 ----------

    public function test_peer_review_total_computed_and_edit_permission(): void
    {
        $reviewer = $this->staffUser();
        $this->grantMenus($reviewer, ['peer-review']);
        $other = User::factory()->create();
        $this->grantMenus($other, ['peer-review']);
        $reviewee = User::factory()->create();

        $res = $this->actingAs($reviewer)->postJson('/api/peer-reviews', [
            'reviewee_id' => $reviewee->id,
            'month' => '2026-08',
            'scores' => [9, 8, 9, 9, 8],
        ]);
        $res->assertJsonPath('data.total', 43)->assertJsonPath('data.can_edit', true);
        $id = $res->json('data.id');

        // 他人的评议：不能改、不能删
        $this->actingAs($other)->putJson("/api/peer-reviews/{$id}", [
            'reviewee_id' => $reviewee->id, 'month' => '2026-08', 'scores' => [1, 1, 1, 1, 1],
        ])->assertForbidden();
        $this->actingAs($other)->deleteJson("/api/peer-reviews/{$id}")->assertForbidden();

        // 本人可改：scores 钳制 0–10，total 重算
        $this->actingAs($reviewer)->putJson("/api/peer-reviews/{$id}", [
            'reviewee_id' => $reviewee->id, 'month' => '2026-08', 'scores' => [10, 10, 9, 9, 9],
        ])->assertJsonPath('data.total', 47);

        // 管理员可删
        $admin = $this->adminUser();
        $this->actingAs($admin)->deleteJson("/api/peer-reviews/{$id}")->assertJsonPath('code', 0);
        $this->assertSoftDeleted('peer_reviews', ['id' => $id]);
    }

    public function test_peer_review_score_out_of_range_rejected(): void
    {
        $reviewer = $this->staffUser();
        $this->grantMenus($reviewer, ['peer-review']);
        $reviewee = User::factory()->create();

        $this->actingAs($reviewer)->postJson('/api/peer-reviews', [
            'reviewee_id' => $reviewee->id, 'month' => '2026-08', 'scores' => [11, 8, 8, 8, 8],
        ])->assertStatus(422)
            ->assertJsonPath('code', 2001)
            ->assertJsonStructure(['data' => ['errors' => ['scores.0']]]);
    }

    // ---------- 工作管理：月度考核统计 + 图表导出 ----------

    public function test_work_assess_stats_year_average_and_month_mode(): void
    {
        $admin = $this->adminUser();
        $u1 = User::factory()->create(['username' => '张三']);
        $u2 = User::factory()->create(['username' => '李四']);

        // u1：7月 90、8月 80 → 平均 85；u2：8月 70
        foreach ([['2026-07', 90, $u1], ['2026-08', 80, $u1], ['2026-08', 70, $u2]] as [$m, $t, $u]) {
            Assessment::create([
                'user_id' => $u->id, 'month' => $m, 'assess_date' => '2026-09-01',
                'items' => [], 'total' => $t, 'grade' => '合格',
            ]);
        }

        $res = $this->actingAs($admin)->getJson('/api/work/assess-stats?year=2026&mode=year');
        $res->assertOk();
        $rows = collect($res->json('data.rows'))->keyBy('name');
        $this->assertSame(85.0, (float) $rows['张三']['score']);
        $this->assertSame(70.0, (float) $rows['李四']['score']);
        $this->assertSame(['2026-07', '2026-08'], $res->json('data.months'));

        // 按月：只取当月得分
        $res2 = $this->actingAs($admin)->getJson('/api/work/assess-stats?year=2026&mode=month&month=2026-08');
        $rows2 = collect($res2->json('data.rows'))->keyBy('name');
        $this->assertSame(80.0, (float) $rows2['张三']['score']);
        $this->assertCount(2, $rows2);
    }

    public function test_work_export_buy_scrap_and_in_use(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)->get('/api/work/export-buy-scrap?year=2026')
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $this->actingAs($admin)->get('/api/work/export-in-use')
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_assessment_export_xlsx(): void
    {
        $admin = $this->adminUser();
        $staff = User::factory()->create();
        Assessment::create([
            'user_id' => $staff->id, 'month' => '2026-08', 'assess_date' => '2026-09-01',
            'items' => [], 'total' => 90, 'grade' => '优秀',
        ]);

        $this->actingAs($admin)->get('/api/assessments/export')
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $this->actingAs($admin)->get('/api/assessment-appeals/export')
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_menu_permission_required(): void
    {
        $staff = $this->staffUser(); // 未勾 assess 菜单
        $this->actingAs($staff)->getJson('/api/assessments')->assertForbidden();
        $this->actingAs($staff)->getJson('/api/assessment-appeals')->assertForbidden();
        $this->actingAs($staff)->getJson('/api/peer-reviews')->assertForbidden();
    }
}
