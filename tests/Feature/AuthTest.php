<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_api_returns_401_with_unified_json(): void
    {
        $res = $this->getJson('/api/me');

        $res->assertStatus(401)
            ->assertJsonPath('code', 1001)
            ->assertJsonPath('message', '未登录或登录已过期');
    }

    public function test_login_with_valid_credentials(): void
    {
        $user = User::factory()->create(['employee_no' => '1001', 'password' => 'secret-123']);

        $res = $this->postJson('/api/login', [
            'employee_no' => '1001',
            'password' => 'secret-123',
        ]);

        $res->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('data.username', $user->username);
    }

    public function test_login_with_wrong_password(): void
    {
        User::factory()->create(['employee_no' => '1002', 'password' => 'secret-123']);

        $res = $this->postJson('/api/login', [
            'employee_no' => '1002',
            'password' => 'wrong-password',
        ]);

        $res->assertStatus(422)
            ->assertJsonPath('code', 1004)
            ->assertJsonPath('message', '工号或密码错误');
    }

    public function test_login_validation_error_format(): void
    {
        $res = $this->postJson('/api/login', []);

        $res->assertStatus(422)
            ->assertJsonPath('code', 2001);
    }

    public function test_logout_invalidates_session(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);
        $this->postJson('/api/logout')->assertOk()->assertJsonPath('code', 0);

        $this->getJson('/api/me')->assertStatus(401);
    }

    public function test_me_returns_current_user_without_password(): void
    {
        $user = User::factory()->create(['employee_no' => '1003']);

        $res = $this->actingAs($user)->getJson('/api/me');

        $res->assertOk()
            ->assertJsonPath('data.employee_no', '1003')
            ->assertJsonMissing(['password']);
    }
}
