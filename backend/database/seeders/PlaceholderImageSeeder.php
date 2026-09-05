<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\ProductImage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PlaceholderImageSeeder extends Seeder
{
    private const CATEGORY_COLORS = [
        'fresh-vegetables' => ['#2d6a4f', '#40916c'],
        'fruits'            => ['#e63946', '#f4845f'],
        'herbs-spices'      => ['#606c38', '#a7c957'],
        'dairy-eggs'        => ['#fca311', '#e5e5e5'],
        'bakery-bread'      => ['#bc6c25', '#dda15e'],
        'grains-pulses'     => ['#9c6644', '#dda15e'],
        'beverages'         => ['#0077b6', '#90e0ef'],
        'snacks-pantry'     => ['#7b2cbf', '#c77dff'],
        'leafy-greens'      => ['#386641', '#6a994e'],
        'root-vegetables'   => ['#b5651d', '#d4a373'],
        'citrus-fruits'     => ['#f77f00', '#fcbf49'],
    ];

    private const PRODUCT_COLORS = [
        'organic-kale'              => '#2d6a4f',
        'organic-spinach'           => '#40916c',
        'heirloom-tomatoes'         => '#e63946',
        'organic-carrots'           => '#f77f00',
        'organic-avocado'           => '#606c38',
        'organic-bananas'           => '#fca311',
        'organic-strawberries'      => '#d62828',
        'organic-lemons'            => '#fcbf49',
        'fresh-basil'               => '#588157',
        'organic-rosemary'          => '#a7c957',
        'organic-mint'              => '#52b788',
        'organic-free-range-eggs'   => '#dda15e',
        'organic-whole-milk'        => '#e5e5e5',
        'sourdough-bread'           => '#bc6c25',
        'organic-whole-wheat-bread' => '#b08968',
        'organic-brown-rice'        => '#9c6644',
        'organic-quinoa'            => '#d4a373',
        'organic-green-tea'         => '#588157',
        'organic-coffee-beans'      => '#3c1518',
        'organic-mixed-nuts'        => '#7b2cbf',
    ];

    public function run(): void
    {
        Storage::disk('public')->makeDirectory('images/categories');
        Storage::disk('public')->makeDirectory('images/products');

        $categories = Category::pluck('icon', 'slug');
        foreach ($categories as $slug => $path) {
            // Keep real category photos: never overwrite an existing icon file
            // (e.g. downloaded .jpg photos) with a color+glyph placeholder.
            if ($path && Storage::disk('public')->exists($path)) {
                continue;
            }

            $colors = self::CATEGORY_COLORS[$slug] ?? ['#6c757d', '#adb5bd'];
            $label  = ucwords(str_replace('-', ' ', $slug));
            $svg    = $this->categorySvg($label, $colors[0], $colors[1]);

            Storage::disk('public')->put($path, $svg);
        }

        $images = ProductImage::with('product:id,slug')->get();
        foreach ($images as $image) {
            // Keep real product photos: never overwrite an existing image file
            // (e.g. downloaded .jpg photos) with a letter placeholder.
            if (Storage::disk('public')->exists($image->image)) {
                continue;
            }

            $slug  = $image->product->slug ?? pathinfo($image->image, PATHINFO_FILENAME);
            $color = self::PRODUCT_COLORS[$slug] ?? '#6c757d';
            $label = ucwords(str_replace('-', ' ', $slug));
            $svg   = $this->productSvg($label, $color);

            Storage::disk('public')->put($image->image, $svg);
        }

        $this->command->info('Placeholder images created in storage/app/public/');
    }

    private function categorySvg(string $label, string $bg, string $accent): string
    {
        $slug = Str::slug($label);
        $glyph = self::CATEGORY_GLYPHS[$slug] ?? $this->defaultGlyph();

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 400" width="400" height="400">
  <defs>
    <linearGradient id="bg" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" stop-color="{$bg}"/>
      <stop offset="100%" stop-color="{$accent}"/>
    </linearGradient>
  </defs>
  <rect width="400" height="400" rx="24" fill="url(#bg)"/>
  <g transform="translate(0,-20)">{$glyph}</g>
</svg>
SVG;
    }

    /**
     * Clean, white, category-specific line pictograms. Each glyph is a centered
     * icon drawn on a 200x200 canvas (origin centered at 100,100).
     */
    private const CATEGORY_GLYPHS = [
        'citrus-fruits' => '
            <g fill="none" stroke="#ffffff" stroke-width="12" stroke-linecap="round">
                <circle cx="100" cy="120" r="58" fill="rgba(255,255,255,0.16)"/>
                <line x1="100" y1="62" x2="78" y2="26"/>
                <line x1="100" y1="62" x2="122" y2="26"/>
                <circle cx="100" cy="120" r="26" fill="none"/>
                <path d="M-8 28 q18 18 36 0 q-8 20 36 8" transform="translate(0,0)"/>
            </g>
            <g fill="none" stroke="#ffffff" stroke-width="8">
                <path d="M100 178 a30 30 0 0 1 0-6" opacity="0.7"/>
            </g>',
        'fresh-vegetables' => '
            <g fill="none" stroke="#ffffff" stroke-width="12" stroke-linecap="round" stroke-linejoin="round">
                <ellipse cx="100" cy="140" rx="58" ry="42" fill="rgba(255,255,255,0.16)"/>
                <path d="M100 98 C 88 70 92 46 100 26 C 108 46 112 70 100 98 Z" opacity="0.9"/>
                <path d="M100 56 C 100 40 104 26 100 8" stroke-width="10" opacity="0.7"/>
            </g>',
        'herbs-spices' => '
            <g fill="none" stroke="#ffffff" stroke-width="11" stroke-linecap="round" stroke-linejoin="round">
                <path d="M100 190 L100 120" stroke-width="13"/>
                <path d="M100 120 C 74 108 66 84 70 62 C 92 66 108 52 122 44 C 124 66 140 82 160 84 C 142 98 128 110 100 120 Z" fill="rgba(255,255,255,0.16)"/>
                <path d="M100 132 C 82 124 74 108 76 92" opacity="0.8"/>
            </g>',
        'dairy-eggs' => '
            <g fill="none" stroke="#ffffff" stroke-width="11" stroke-linecap="round" stroke-linejoin="round">
                <rect x="56" y="96" width="88" height="66" rx="10" fill="rgba(255,255,255,0.16)"/>
                <path d="M56 124 L144 124"/>
                <path d="M100 96 L100 78 M88 78 L112 78"/>
                <ellipse cx="132" cy="176" rx="20" ry="14" transform="rotate(20 132 176)"/>
            </g>',
        'bakery-bread' => '
            <g fill="none" stroke="#ffffff" stroke-width="11" stroke-linecap="round" stroke-linejoin="round">
                <path d="M44 150 Q44 98 100 98 Q156 98 156 150 Z" fill="rgba(255,255,255,0.16)"/>
                <path d="M58 150 L142 150"/>
                <path d="M60 130 Q80 120 100 130 M100 130 Q120 140 144 128"/>
            </g>',
        'grains-pulses' => '
            <g fill="none" stroke="#ffffff" stroke-width="10" stroke-linecap="round">
                <ellipse cx="82" cy="110" rx="26" ry="34" transform="rotate(-18 82 110)" fill="rgba(255,255,255,0.16)"/>
                <ellipse cx="128" cy="116" rx="24" ry="32" transform="rotate(14 128 116)"/>
                <path d="M72 88 q-6 -16 -20 -18 q2 18 16 22"/>
                <path d="M120 92 q-6 -14 -20 -16 q2 16 16 20"/>
                <path d="M70 132 q4 12 14 18" opacity="0.7"/>
            </g>',
        'beverages' => '
            <g fill="none" stroke="#ffffff" stroke-width="11" stroke-linecap="round" stroke-linejoin="round">
                <path d="M84 96 h72 l-12 78 h-48 Z" fill="rgba(255,255,255,0.16)"/>
                <path d="M92 96 a26 26 0 0 1 48 0"/>
                <path d="M106 150 q6 4 12 0 M118 158 q6 4 12 0" opacity="0.8"/>
            </g>',
        'snacks-pantry' => '
            <g fill="none" stroke="#ffffff" stroke-width="11" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="100" cy="100" r="44" fill="rgba(255,255,255,0.16)"/>
                <circle cx="100" cy="100" r="28"/>
                <circle cx="100" cy="100" r="6" fill="#ffffff"/>
                <path d="M100 56 A44 44 0 0 1 130 132" opacity="0.5"/>
            </g>',
        'leafy-greens' => '
            <g fill="none" stroke="#ffffff" stroke-width="11" stroke-linecap="round" stroke-linejoin="round">
                <path d="M100 176 C 56 176 40 128 40 92 C 76 92 128 80 158 58 C 144 100 148 150 100 176 Z" fill="rgba(255,255,255,0.16)"/>
                <path d="M100 176 L100 96" opacity="0.7"/>
                <path d="M100 116 L72 92 M100 126 L128 100" opacity="0.7"/>
            </g>',
        'root-vegetables' => '
            <g fill="none" stroke="#ffffff" stroke-width="12" stroke-linecap="round" stroke-linejoin="round">
                <path d="M72 120 Q70 40 120 28 Q172 38 130 120 Z" fill="rgba(255,255,255,0.16)" transform="rotate(14 100 74)"/>
                <path d="M80 70 L44 40 M94 54 L66 18" opacity="0.8" stroke-width="9"/>
                <path d="M120 150 L134 190 M88 156 L70 192" stroke-width="9" opacity="0.7"/>
            </g>',
        'fruits' => '
            <g fill="none" stroke="#ffffff" stroke-width="11" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="100" cy="124" r="44" fill="rgba(255,255,255,0.16)"/>
                <path d="M100 80 q-4 -18 12 -26 q4 18 -12 26 Z"/>
                <circle cx="100" cy="124" r="6" fill="#ffffff"/>
                <path d="M120 100 a34 34 0 0 1 -8 22 q10 -2 10 -12" opacity="0.7"/>
            </g>',
    ];

    private function defaultGlyph(): string
    {
        return '<circle cx="100" cy="100" r="44" fill="rgba(255,255,255,0.16)"/>'
            .'<circle cx="100" cy="100" r="30" fill="none" stroke="#ffffff" stroke-width="11"/>'
            .'<path d="M100 70 L100 130 M70 100 L130 100" stroke="#ffffff" stroke-width="11" stroke-linecap="round"/>';
    }

    private function productSvg(string $label, string $bg): string
    {
        $initial = strtoupper(mb_substr($label, 0, 1));

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 600 600" width="600" height="600">
  <defs>
    <linearGradient id="bg" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" stop-color="{$bg}"/>
      <stop offset="100%" stop-color="{$bg}" stop-opacity="0.7"/>
    </linearGradient>
  </defs>
  <rect width="600" height="600" rx="16" fill="url(#bg)"/>
  <circle cx="300" cy="240" r="100" fill="rgba(255,255,255,0.12)"/>
  <text x="300" y="280" text-anchor="middle" font-family="system-ui,sans-serif" font-size="140" font-weight="700" fill="rgba(255,255,255,0.3)">{$initial}</text>
  <text x="300" y="400" text-anchor="middle" font-family="system-ui,sans-serif" font-size="26" font-weight="600" fill="rgba(255,255,255,0.9)">{$label}</text>
</svg>
SVG;
    }
}
