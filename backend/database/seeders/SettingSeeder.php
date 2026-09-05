<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            ['key' => 'store.name', 'value' => 'Organic Store', 'group' => 'store', 'is_public' => true],
            ['key' => 'store.tagline', 'value' => 'Fresh organic food delivered to your door', 'group' => 'store', 'is_public' => true],
            ['key' => 'store.currency', 'value' => 'PHP', 'group' => 'store', 'is_public' => true],
            ['key' => 'store.logo', 'value' => '', 'group' => 'store', 'is_public' => true],
            ['key' => 'hero_banner.url', 'value' => 'images/hero-banner.png', 'group' => 'hero', 'is_public' => true],
            ['key' => 'shipping.flat_rate', 'value' => '5.00', 'group' => 'shipping', 'is_public' => true],
            ['key' => 'shipping.free_over', 'value' => '50.00', 'group' => 'shipping', 'is_public' => true],
            ['key' => 'tax.rate', 'value' => '0.08', 'group' => 'tax', 'is_public' => false],
            ['key' => 'general.low_stock_threshold', 'value' => '5', 'group' => 'general', 'is_public' => false],
            ['key' => 'general.currency_symbol', 'value' => '₱', 'group' => 'general', 'is_public' => true],
        ];

        foreach ($settings as $s) {
            Setting::updateOrCreate(['key' => $s['key']], $s);
        }
    }
}
