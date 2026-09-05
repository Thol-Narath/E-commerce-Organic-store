<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    /**
     * Category icon filenames served from public/category-icons/.
     *
     * These are real food photos (JPEG) reused from the product image set so
     * the category row reads as clean, recognizable product photography. New
     * categories can be added here with their own file without touching the
     * React frontend.
     */
    private array $icons = [
        'Citrus Fruits' => 'category-icons/citrus.jpg',
        'Fresh Vegetables' => 'category-icons/vegetables.jpg',
        'Leafy Greens' => 'category-icons/leafy-greens.jpg',
        'Root Vegetables' => 'category-icons/root-vegetables.jpg',
        'Fruits' => 'category-icons/fruits.jpg',
        'Herbs & Spices' => 'category-icons/herbs-spices.jpg',
        'Dairy & Eggs' => 'category-icons/dairy-eggs.jpg',
        'Bakery & Bread' => 'category-icons/bakery.jpg',
        'Grains & Pulses' => 'category-icons/grains.jpg',
        'Beverages' => 'category-icons/beverages.jpg',
        'Snacks & Pantry' => 'category-icons/snacks.jpg',
    ];

    private function iconFor(string $name): ?string
    {
        return $this->icons[$name] ?? null;
    }

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
                'icon' => $this->iconFor($name),
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
                'icon' => $this->iconFor($name),
                'status' => 'active',
                'sort_order' => 1,
            ]);
        }
    }
}
