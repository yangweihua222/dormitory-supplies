<?php

namespace Database\Factories;

use App\Models\Schedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Schedule>
 */
class ScheduleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => UserFactory::new(),
            'date' => now()->toDateString(),
            'shift_id' => ShiftFactory::new(),
            'status' => 'normal',
        ];
    }

    public function leave(): static
    {
        return $this->state(fn () => ['status' => 'leave']);
    }
}
