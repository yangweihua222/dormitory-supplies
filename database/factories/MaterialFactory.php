<?php

namespace Database\Factories;

use App\Models\Material;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Material>
 */
class MaterialFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kind' => 'fixed',
            'consumable_sub' => null,
            'name' => '测试物资',
            'purchase_year' => now()->year,
            'scrapped' => false,
            'location' => '101',
            'use_type' => 'office',
            'qty' => 1,
            'stock' => 1,
        ];
    }

    public function consumable(string $sub = 'other'): static
    {
        return $this->state(fn () => [
            'kind' => 'consumable',
            'consumable_sub' => $sub,
        ]);
    }
}
