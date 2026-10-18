<?php

namespace Tests\Unit;

use App\Enums\Identity;
use App\Models\User;
use PHPUnit\Framework\TestCase;

class UserModelTest extends TestCase
{
    private function makeUser(string $identity, string $username = '张三'): User
    {
        $user = new User;
        $user->username = $username;
        $user->identity = $identity;

        return $user;
    }

    public function test_is_admin(): void
    {
        $this->assertTrue($this->makeUser('admin')->isAdmin());
        $this->assertFalse($this->makeUser('supervisor')->isAdmin());
        $this->assertFalse($this->makeUser('staff')->isAdmin());
    }

    public function test_repair_follower_rules(): void
    {
        $follower = $this->makeUser('staff', '徐长东');
        $this->assertTrue($follower->isRepairFollower());
        $this->assertTrue($follower->canEditRepairStatus());
        $this->assertTrue($follower->canSeeMaterialAlerts());

        $staff = $this->makeUser('staff', '普通员工');
        $this->assertFalse($staff->isRepairFollower());
        $this->assertFalse($staff->canEditRepairStatus());
        $this->assertFalse($staff->canSeeMaterialAlerts());

        // 管理员不是维修负责人，但能改维修情况
        $admin = $this->makeUser('admin', '管理员');
        $this->assertFalse($admin->isRepairFollower());
        $this->assertTrue($admin->canEditRepairStatus());
    }

    public function test_identity_enum_values(): void
    {
        $this->assertSame(['admin', 'supervisor', 'staff'], array_column(Identity::cases(), 'value'));
    }
}
