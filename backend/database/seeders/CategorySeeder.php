<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'Fresh Vegetables',
            'Fruits',
            'Herbs & Spices',
            'Dairy & Eggs',
            'Bakery & Bread',
            'Grains & Pulses',
            'Beverages',
            'Snacks & Pantry',
        ];

        foreach ($categories as $name) {
            Category::create([
                'parent_id' => null,
                'name' => $name,
                'slug' => Str::slug($name),
                'description' => "Organic $name grown and curated with care.",
                'icon' => 'images/categories/'.Str::slug($name).'.svg',
                'status' => 'active',
                'sort_order' => array_search($name, $categories) + 1,
            ]);
        }

        $subCategories = [
            'Leafy Greens' => 'Fresh Vegetables',
            'Root Vegetables' => 'Fresh Vegetables',
            'Citrus Fruits' => 'Fruits',
        ];

        foreach ($subCategories as $name => $parentName) {
            $parent = Category::where('name', $parentName)->first();
            Category::create([
                'parent_id' => $parent->id,
                'name' => $name,
                'slug' => Str::slug($name),
                'description' => "Organic $name.",
                'icon' => 'images/categories/'.Str::slug($name).'.svg',
                'status' => 'active',
                'sort_order' => 1,
            ]);
        }
    }
}
