<?php

namespace Tests\Feature;

use App\Models\Material;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 物资台账（需求 §8）：字典选项、校验、增改删。
 */
class MaterialTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'kind' => 'consumable',
            'consumable_sub' => 'other',
            'name' => '测试耗材',
            'purchase_year' => 2026,
            'scrapped' => false,
            'location' => '库房',
            'use_type' => 'office',
            'qty' => 10,
        ], $overrides);
    }

    public function test_options_include_other_sub(): void
    {
        $user = $this->staffUser();

        $res = $this->actingAs($user)->getJson('/api/materials/options');

        $res->assertOk()
            ->assertJsonPath('code', 0);

        $subs = collect($res->json('data.consumable_subs'))->pluck('value');
        $this->assertContains('other', $subs);
        $this->assertCount(6, $subs);
    }

    public function test_store_consumable_with_other_sub(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)->postJson('/api/materials', $this->payload())
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('message', '已添加');

        $this->assertDatabaseHas('materials', [
            'name' => '测试耗材',
            'kind' => 'consumable',
            'consumable_sub' => 'other',
        ]);
    }

    public function test_store_consumable_requires_sub(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)->postJson('/api/materials', $this->payload(['consumable_sub' => null]))
            ->assertStatus(422)
            ->assertJsonPath('code', 2001)
            ->assertJsonPath('message', '耗材物资请选择耗材子类');
    }

    public function test_store_rejects_invalid_sub(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)->postJson('/api/materials', $this->payload(['consumable_sub' => 'unknown']))
            ->assertStatus(422);
    }

    public function test_store_fixed_asset_ignores_sub(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)->postJson('/api/materials', $this->payload([
            'kind' => 'fixed',
            'consumable_sub' => 'other',
        ]))->assertOk()->assertJsonPath('code', 0);

        // 固定资产入库后子类清空
        $material = Material::where('name', '测试耗材')->first();
        $this->assertSame('fixed', $material->kind->value);
        $this->assertNull($material->consumable_sub);
    }

    public function test_list_filters_by_kind(): void
    {
        $admin = $this->adminUser();
        Material::factory()->count(2)->create();
        Material::factory()->consumable('other')->create(['name' => '其他类耗材']);

        $res = $this->actingAs($admin)->getJson('/api/materials?kind=consumable');

        $res->assertOk()->assertJsonPath('data.total', 1);
        $this->assertSame('其他类耗材', $res->json('data.list.0.name'));
    }

    public function test_update_and_delete(): void
    {
        $admin = $this->adminUser();
        $material = Material::factory()->create();

        $this->actingAs($admin)->putJson("/api/materials/{$material->id}", $this->payload([
            'name' => '改名后的物资',
        ]))->assertOk()->assertJsonPath('code', 0);

        $this->assertDatabaseHas('materials', ['id' => $material->id, 'name' => '改名后的物资']);

        $this->actingAs($admin)->deleteJson("/api/materials/{$material->id}")
            ->assertOk()->assertJsonPath('code', 0);

        $this->assertSoftDeleted('materials', ['id' => $material->id]);
    }

    public function test_store_requires_material_menu(): void
    {
        $user = $this->grantMenus(User::factory()->create(), 'attendance'); // 有别的菜单但没有物资菜单

        $this->actingAs($user)->postJson('/api/materials', $this->payload())
            ->assertStatus(403)
            ->assertJsonPath('code', 1002);
    }
}
