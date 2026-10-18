<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    private static int $sequence = 0;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $seq = ++self::$sequence;

        return [
            'username' => '测试用户'.$seq,
            'employee_no' => sprintf('9%03d', $seq),
            'phone' => '138'.str_pad((string) $seq, 8, '0', STR_PAD_LEFT),
            'identity' => 'staff',
            'password' => 'password-123456',
        ];
    }

    public function admin(): static
    {
        return $this->state(fn () => ['identity' => 'admin']);
    }

    public function supervisor(): static
    {
        return $this->state(fn () => ['identity' => 'supervisor']);
    }

    /** 维修负责人（原型口径：以姓名白名单识别） */
    public function repairFollower(): static
    {
        return $this->state(fn () => ['username' => '徐长东']);
    }
}
