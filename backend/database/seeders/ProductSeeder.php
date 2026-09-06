<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $categoryId = function (string $name): int {
            return Category::where('name', $name)->firstOrFail()->id;
        };

        $products = [
            [
                'name' => 'Organic Kale', 'category' => 'Fresh Vegetables', 'price' => 2.50, 'cost' => 1.20,
                'stock' => 60, 'unit' => 'bunch', 'weight' => 0.30, 'desc' => 'Freshly harvested organic kale, rich in vitamins and minerals.',
                'short' => 'Crisp, nutrient-dense organic kale.',
                'best_seller' => true,
            ],
            [
                'name' => 'Organic Spinach', 'category' => 'Fresh Vegetables', 'price' => 2.00, 'cost' => 0.90,
                'stock' => 80, 'unit' => 'bunch', 'weight' => 0.25, 'desc' => 'Tender organic spinach leaves, perfect for salads and smoothies.',
                'short' => 'Tender organic leaves for any dish.',
            ],
            [
                'name' => 'Heirloom Tomatoes', 'category' => 'Fresh Vegetables', 'price' => 3.80, 'cost' => 1.90,
                'stock' => 45, 'unit' => 'kg', 'weight' => 1.00, 'desc' => 'Sweet, vibrant heirloom tomatoes grown without synthetic pesticides.',
                'short' => 'Sweet, colorful heirloom tomatoes.',
            ],
            [
                'name' => 'Organic Carrots', 'category' => 'Fresh Vegetables', 'price' => 1.80, 'cost' => 0.80,
                'stock' => 100, 'unit' => 'kg', 'weight' => 1.00, 'desc' => 'Crunchy organic carrots, high in beta-carotene.',
                'short' => 'Crunchy, sweet organic carrots.',
            ],
            [
                'name' => 'Organic Avocado', 'category' => 'Fruits', 'price' => 2.20, 'cost' => 1.00,
                'stock' => 40, 'unit' => 'pcs', 'weight' => 0.30, 'desc' => 'Creamy organic avocados, perfect for toast and salads.',
                'short' => 'Creamy, ripe organic avocados.',
                'featured' => true,
                'best_seller' => true,
            ],
            [
                'name' => 'Organic Bananas', 'category' => 'Fruits', 'price' => 1.50, 'cost' => 0.60,
                'stock' => 70, 'unit' => 'kg', 'weight' => 1.00, 'desc' => 'Naturally ripe organic bananas, a great source of potassium.',
                'short' => 'Sweet naturally ripe bananas.',
            ],
            [
                'name' => 'Organic Strawberries', 'category' => 'Fruits', 'price' => 4.50, 'cost' => 2.30,
                'stock' => 30, 'unit' => 'pack', 'weight' => 0.50, 'desc' => 'Juicy organic strawberries, sweet and fragrant.',
                'short' => 'Juicy, fragrant organic strawberries.',
                'featured' => true,
                'best_seller' => true,
            ],
            [
                'name' => 'Organic Lemons', 'category' => 'Fruits', 'price' => 1.20, 'cost' => 0.50,
                'stock' => 90, 'unit' => 'kg', 'weight' => 1.00, 'desc' => 'Zesty organic lemons for cooking and fresh lemonade.',
                'short' => 'Zesty fresh organic lemons.',
            ],
            [
                'name' => 'Fresh Basil', 'category' => 'Herbs & Spices', 'price' => 1.50, 'cost' => 0.60,
                'stock' => 50, 'unit' => 'bunch', 'weight' => 0.10, 'desc' => 'Aromatic organic basil, ideal for pasta and salads.',
                'short' => 'Aromatic fresh basil leaves.',
            ],
            [
                'name' => 'Organic Rosemary', 'category' => 'Herbs & Spices', 'price' => 1.80, 'cost' => 0.70,
                'stock' => 35, 'unit' => 'bunch', 'weight' => 0.10, 'desc' => 'Fragrant organic rosemary for roasts and marinades.',
                'short' => 'Fragrant organic rosemary sprigs.',
            ],
            [
                'name' => 'Organic Mint', 'category' => 'Herbs & Spices', 'price' => 1.40, 'cost' => 0.55,
                'stock' => 55, 'unit' => 'bunch', 'weight' => 0.10, 'desc' => 'Cooling organic mint for teas and desserts.',
                'short' => 'Cooling fresh organic mint.',
            ],
            [
                'name' => 'Organic Free-Range Eggs', 'category' => 'Dairy & Eggs', 'price' => 4.00, 'cost' => 2.20,
                'stock' => 60, 'unit' => 'dozen', 'weight' => 0.70, 'desc' => 'Free-range organic eggs from pasture-raised hens.',
                'short' => 'Organic free-range eggs (dozen).',
                'featured' => true,
                'best_seller' => true,
            ],
            [
                'name' => 'Organic Whole Milk', 'category' => 'Dairy & Eggs', 'price' => 3.20, 'cost' => 1.70,
                'stock' => 50, 'unit' => 'litre', 'weight' => 1.00, 'desc' => 'Creamy organic whole milk, hormone-free.',
                'short' => 'Creamy hormone-free organic milk.',
            ],
            [
                'name' => 'Sourdough Bread', 'category' => 'Bakery & Bread', 'price' => 5.00, 'cost' => 2.50,
                'stock' => 25, 'unit' => 'loaf', 'weight' => 0.90, 'desc' => 'Artisan organic sourdough, slow-fermented for flavor.',
                'short' => 'Artisan slow-fermented sourdough.',
                'featured' => true,
                'best_seller' => true,
            ],
            [
                'name' => 'Organic Whole Wheat Bread', 'category' => 'Bakery & Bread', 'price' => 3.50, 'cost' => 1.80,
                'stock' => 40, 'unit' => 'loaf', 'weight' => 0.80, 'desc' => 'Hearty organic whole wheat bread, freshly baked.',
                'short' => 'Freshly baked whole wheat bread.',
            ],
            [
                'name' => 'Organic Brown Rice', 'category' => 'Grains & Pulses', 'price' => 3.00, 'cost' => 1.40,
                'stock' => 85, 'unit' => 'kg', 'weight' => 1.00, 'desc' => 'Nutritious organic brown rice, high in fiber.',
                'short' => 'High-fiber organic brown rice.',
            ],
            [
                'name' => 'Organic Quinoa', 'category' => 'Grains & Pulses', 'price' => 6.50, 'cost' => 3.20,
                'stock' => 45, 'unit' => 'kg', 'weight' => 1.00, 'desc' => 'Complete-protein organic quinoa, gluten-free.',
                'short' => 'Gluten-free complete protein quinoa.',
                'featured' => true,
                'best_seller' => true,
            ],
            [
                'name' => 'Organic Green Tea', 'category' => 'Beverages', 'price' => 4.80, 'cost' => 2.40,
                'stock' => 60, 'unit' => 'pack', 'weight' => 0.10, 'desc' => 'Antioxidant-rich organic green tea leaves.',
                'short' => 'Antioxidant-rich organic green tea.',
            ],
            [
                'name' => 'Organic Coffee Beans', 'category' => 'Beverages', 'price' => 9.00, 'cost' => 4.50,
                'stock' => 35, 'unit' => 'kg', 'weight' => 1.00, 'desc' => 'Bold organic coffee beans, fair-trade roasted.',
                'short' => 'Bold fair-trade organic coffee.',
                'featured' => true,
            ],
            [
                'name' => 'Organic Mixed Nuts', 'category' => 'Snacks & Pantry', 'price' => 7.50, 'cost' => 3.80,
                'stock' => 50, 'unit' => 'pack', 'weight' => 0.40, 'desc' => 'Crunchy organic mixed nuts, great for snacking.',
                'short' => 'Crunchy organic mixed nuts.',
            ],
        ];

        foreach ($products as $i => $data) {
            $product = Product::create([
                'category_id' => $categoryId($data['category']),
                'name' => $data['name'],
                'slug' => Str::slug($data['name']),
                'description' => $data['desc'],
                'short_description' => $data['short'],
                'sku' => 'ORG-'.str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT),
                'barcode' => '4800'.str_pad((string) ($i + 1), 8, '0', STR_PAD_LEFT),
                'price' => $data['price'],
                'compare_at_price' => $data['featured'] ?? false ? $data['price'] * 1.2 : null,
                'cost_price' => $data['cost'],
                'stock_quantity' => $data['stock'],
                'low_stock_threshold' => 5,
                'is_featured' => $data['featured'] ?? false,
                'is_best_seller' => $data['best_seller'] ?? false,
                'status' => 'active',
                'unit' => $data['unit'],
                'weight' => $data['weight'],
                'min_order_qty' => 1,
            ]);

            ProductImage::create([
                'product_id' => $product->id,
                'image' => 'images/products/'.$product->slug.'.jpg',
                'alt_text' => $data['name'],
                'sort_order' => 1,
                'is_primary' => true,
            ]);

            InventoryTransaction::create([
                'product_id' => $product->id,
                'user_id' => null,
                'type' => 'initial',
                'quantity_change' => $data['stock'],
                'stock_before' => 0,
                'stock_after' => $data['stock'],
                'reference_type' => 'seeder',
                'reference_id' => null,
                'notes' => "Initial stock on hand for {$data['name']}.",
            ]);
        }
    }
}
