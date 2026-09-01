<?php

namespace Database\Factories;

use App\Models\WishlistItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WishlistItem>
 */
class WishlistItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'wishlist_id' => 1,
            'product_id' => 1,
        ];
    }
}
