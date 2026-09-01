<?php

namespace Database\Factories;

use App\Models\Address;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Address>
 */
class AddressFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => 1,
            'label' => fake()->randomElement(['Home', 'Work', 'Other']),
            'recipient_name' => fake()->name(),
            'recipient_phone' => fake()->numerify('##########'),
            'address_line1' => fake()->streetAddress(),
            'address_line2' => fake()->boolean(30) ? fake()->secondaryAddress() : null,
            'city' => fake()->city(),
            'state' => fake()->state(),
            'postal_code' => fake()->postcode(),
            'country' => fake()->country(),
            'is_default' => false,
        ];
    }
}
