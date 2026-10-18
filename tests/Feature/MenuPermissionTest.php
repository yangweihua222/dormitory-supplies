<?php

namespace Tests\Feature;

use App\Models\Material;
use App\Models\Schedule;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 菜单权限中间件（技术方案 §4.2）：menu:xxx 拦截 + 字典豁免。
 */
class MenuPermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_menu_guarded_route_rejects_user_without_permission(): void
    {
        $user = $this->staffUser();

        $this->actingAs($user)->getJson('/api/materials')
            ->assertStatus(403)
            ->assertJsonPath('code', 1002)
            ->assertJsonPath('message', '无该菜单权限');
    }

    public function test_menu_guarded_route_allows_user_with_permission(): void
    {
        $user = $this->grantMenus(User::factory()->create(), 'material');
        Material::factory()->create();

        $this->actingAs($user)->getJson('/api/materials')
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('data.total', 1);
    }

    public function test_dictionary_options_do_not_require_menu(): void
    {
        $user = $this->staffUser();

        // 字典接口登录即可访问
        $this->actingAs($user)->getJson('/api/materials/options')
            ->assertOk()
            ->assertJsonPath('code', 0);

        $this->actingAs($user)->getJson('/api/leaves/options')
            ->assertOk()
            ->assertJsonPath('code', 0);
    }

    public function test_attendance_list_scoped_for_non_admin(): void
    {
        // 非管理员只能看自己的考勤
        $user = $this->grantMenus(User::factory()->create(), 'attendance');
        $other = User::factory()->create();
        $shift = Shift::factory()->create();
        Schedule::factory()->create(['user_id' => $user->id, 'shift_id' => $shift->id]);
        Schedule::factory()->create(['user_id' => $other->id, 'shift_id' => $shift->id]);

        $res = $this->actingAs($user)->getJson('/api/attendance?start_date='.now()->toDateString().'&end_date='.now()->toDateString());

        $res->assertOk()->assertJsonPath('data.total', 1);
        $this->assertSame($user->username, $res->json('data.list.0.username'));
    }
}
