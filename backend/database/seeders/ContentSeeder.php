<?php

namespace Database\Seeders;

use App\Models\Banner;
use App\Models\Blog;
use App\Models\Testimonial;
use Illuminate\Database\Seeder;

class ContentSeeder extends Seeder
{
    public function run(): void
    {
        // Banners (hero slides + promo cards)
        Banner::insert([
            [
                'title' => 'Fresh Organic Vegetables',
                'subtitle' => 'Farm to table goodness delivered to your door',
                'discount_percent' => null,
                'discount_label' => '25% OFF',
                'image_url' => null,
                'link_url' => '/shop',
                'bg_color' => '#f97316',
                'cta_text' => 'Shop Now',
                'cta_link' => '/shop',
                'is_active' => true,
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Summer Fruit Sale',
                'subtitle' => 'Seasonal specials up to 30% off',
                'discount_percent' => null,
                'discount_label' => '30% OFF',
                'image_url' => null,
                'link_url' => '/shop',
                'bg_color' => '#16a34a',
                'cta_text' => 'View Deals',
                'cta_link' => '/shop',
                'is_active' => true,
                'sort_order' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Organic Breakfast Bundle',
                'subtitle' => 'Start your day right with fresh organic produce',
                'discount_percent' => null,
                'discount_label' => '15% OFF',
                'image_url' => null,
                'link_url' => '/shop',
                'bg_color' => '#2e7d32',
                'cta_text' => 'Order Now',
                'cta_link' => '/shop',
                'is_active' => true,
                'sort_order' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Weekend Specials',
                'subtitle' => 'Everyday fresh & clean products',
                'discount_percent' => null,
                'discount_label' => '20% OFF',
                'image_url' => null,
                'link_url' => '/shop',
                'bg_color' => '#ea580c',
                'cta_text' => 'Shop Now',
                'cta_link' => '/shop',
                'is_active' => true,
                'sort_order' => 4,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // Testimonials
        Testimonial::insert([
            [
                'name' => 'Sarah Johnson',
                'role' => 'Regular Customer',
                'avatar_url' => null,
                'rating' => 5,
                'quote' => 'The freshest organic produce I have ever found online. Delivery is always on time and the quality is consistently excellent!',
                'is_active' => true,
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Michael Chen',
                'role' => 'Food Blogger',
                'avatar_url' => null,
                'rating' => 5,
                'quote' => 'As a food blogger, I need the best ingredients. This store never disappoints. The vegetables are crisp and the fruits are perfectly ripe.',
                'is_active' => true,
                'sort_order' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Emily Davis',
                'role' => 'Health Enthusiast',
                'avatar_url' => null,
                'rating' => 5,
                'quote' => 'I love that everything is certified organic. The prices are fair and the customer service is outstanding. Highly recommended!',
                'is_active' => true,
                'sort_order' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'David Wilson',
                'role' => 'Home Chef',
                'avatar_url' => null,
                'rating' => 4,
                'quote' => 'Great selection of organic products. The website is easy to navigate and checkout is seamless. Will definitely order again.',
                'is_active' => true,
                'sort_order' => 4,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // Blog posts
        Blog::insert([
            [
                'title' => 'The Benefits of Eating Organic',
                'slug' => 'benefits-of-eating-organic',
                'excerpt' => 'Discover why switching to organic food can improve your health and support sustainable farming practices.',
                'content' => 'Eating organic food has numerous benefits for both your health and the environment. Organic farming avoids synthetic pesticides and fertilizers, resulting in cleaner produce...',
                'image_url' => null,
                'author_name' => 'Organic Store Team',
                'category' => 'Health',
                'is_published' => true,
                'published_at' => now()->subDays(2),
                'created_at' => now()->subDays(2),
                'updated_at' => now()->subDays(2),
            ],
            [
                'title' => '10 Easy Organic Recipes for Busy Weeknights',
                'slug' => 'easy-organic-weeknight-recipes',
                'excerpt' => 'Simple, delicious recipes using fresh organic ingredients that can be prepared in under 30 minutes.',
                'content' => 'Cooking healthy meals does not have to be time-consuming. Here are 10 quick and easy recipes that use fresh organic ingredients...',
                'image_url' => null,
                'author_name' => 'Chef Maria',
                'category' => 'Recipes',
                'is_published' => true,
                'published_at' => now()->subDays(5),
                'created_at' => now()->subDays(5),
                'updated_at' => now()->subDays(5),
            ],
            [
                'title' => 'Seasonal Produce Guide: What to Buy This Month',
                'slug' => 'seasonal-produce-guide',
                'excerpt' => 'A comprehensive guide to the freshest seasonal produce available this month and how to make the most of it.',
                'content' => 'Shopping for seasonal produce is not only better for the environment but also ensures you get the freshest and most flavorful ingredients...',
                'image_url' => null,
                'author_name' => 'Organic Store Team',
                'category' => 'Guide',
                'is_published' => true,
                'published_at' => now()->subDays(8),
                'created_at' => now()->subDays(8),
                'updated_at' => now()->subDays(8),
            ],
        ]);
    }
}
