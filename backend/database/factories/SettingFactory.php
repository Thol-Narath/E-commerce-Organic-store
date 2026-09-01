<?php

namespace Database\Factories;

use App\Models\Setting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Setting>
 */
class SettingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'key' => fake()->unique()->bothify('setting_##'),
            'value' => fake()->sentence(),
            'group' => fake()->randomElement(['store', 'shipping', 'tax', 'general']),
            'is_public' => false,
        ];
    }
}
