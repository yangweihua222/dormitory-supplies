<?php

namespace Database\Factories;

use App\Models\Shift;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shift>
 */
class ShiftFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => '早班',
            'check_in_time' => '08:00:00',
            'check_out_time' => '17:00:00',
            'type' => 'normal',
        ];
    }

    public function duty(): static
    {
        return $this->state(fn () => [
            'name' => '值班',
            'check_in_time' => '22:00:00',
            'check_out_time' => '08:00:00',
            'type' => 'duty',
        ]);
    }
}
