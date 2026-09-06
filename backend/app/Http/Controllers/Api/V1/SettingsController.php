<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SettingsController extends Controller
{
    use ApiResponse;

    /**
     * GET /api/v1/settings/public — public store information used by the
     * customer frontend (display only). Reads key/value pairs from the
     * settings table where is_public = true and augments them with
     * computed URLs (e.g. hero banner).
     */
    public function publicSettings(): JsonResponse
    {
        $rows = Setting::where('is_public', true)
            ->get()
            ->keyBy('key');

        $heroUrl = $rows->get('hero_banner.url')->value ?? null;
        $logoPath = $rows->get('store.logo')->value ?? null;

        if ($heroUrl && !str_starts_with($heroUrl, 'http')) {
            $heroUrl = url('storage/' . ltrim($heroUrl, '/'));
        }

        $logoUrl = null;
        if ($logoPath) {
            $logoUrl = str_starts_with($logoPath, 'http')
                ? $logoPath
                : url('storage/' . ltrim($logoPath, '/'));
        }

        return $this->success([
            'store' => [
                'name' => $rows->get('store.name')->value ?? config('app.name', 'Organic Store'),
                'tagline' => $rows->get('store.tagline')->value ?? '',
                'currency' => $rows->get('store.currency')->value ?? 'USD',
                'currency_symbol' => $rows->get('general.currency_symbol')->value ?? '$',
                'logo_height' => (int) ($rows->get('store.logo_height')->value ?? 42),
                'logo' => $logoUrl,
            ],
            'shipping' => [
                'flat_rate' => number_format((float) ($rows->get('shipping.flat_rate')->value ?? 0), 2, '.', ''),
                'free_over' => number_format((float) ($rows->get('shipping.free_over')->value ?? 0), 2, '.', ''),
            ],
            'hero_banner_url' => $heroUrl,
        ]);
    }

    /**
     * PUT /api/v1/admin/settings/store-branding — update editable store identity
     * used across the customer storefront (admin only): display name, tagline
     * and logo size (in pixels, applied by the header).
     */
    public function updateStoreBranding(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'tagline' => ['nullable', 'string', 'max:255'],
            'logo_height' => ['required', 'integer', 'min:16', 'max:240'],
        ]);

        $values = [
            'store.name' => trim($validated['name']),
            'store.tagline' => trim($validated['tagline'] ?? ''),
            'store.logo_height' => (string) $validated['logo_height'],
        ];

        foreach ($values as $key => $value) {
            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => $value, 'group' => 'store', 'is_public' => true]
            );
        }

        return $this->success([
            'name' => $values['store.name'],
            'tagline' => $values['store.tagline'],
            'logo_height' => (int) $values['store.logo_height'],
        ], 'Store branding updated successfully.');
    }

    /**
     * GET /api/v1/admin/settings/logo — current store logo (admin only).
     */
    public function getLogo(): JsonResponse
    {
        $path = Setting::where('key', 'store.logo')->value('value');

        return $this->success([
            'logo' => $path ? url('storage/' . ltrim($path, '/')) : null,
            'is_set' => (bool) $path,
        ], 'Logo retrieved successfully.');
    }

    /**
     * POST /api/v1/admin/settings/logo — upload/replace the store logo (admin only).
     * Expects a multipart field `logo` (png, jpg, jpeg, webp or svg).
     */
    public function uploadLogo(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'logo' => 'required|image|mimes:png,jpg,jpeg,webp,svg|max:2048',
        ]);

        $file = $validated['logo'];

        // Remove the previous logo file if it lives on the public disk.
        $oldPath = Setting::where('key', 'store.logo')->value('value');
        if ($oldPath && !str_starts_with($oldPath, 'http')) {
            Storage::disk('public')->delete($oldPath);
        }

        $path = $file->store('logos', 'public');

        Setting::updateOrCreate(
            ['key' => 'store.logo'],
            ['value' => $path, 'group' => 'store', 'is_public' => true]
        );

        return $this->success(
            ['logo' => url('storage/' . ltrim($path, '/')), 'is_set' => true],
            'Logo updated successfully.'
        );
    }

    /**
     * DELETE /api/v1/admin/settings/logo — remove the store logo (admin only).
     */
    public function removeLogo(): JsonResponse
    {
        $oldPath = Setting::where('key', 'store.logo')->value('value');
        if ($oldPath && !str_starts_with($oldPath, 'http')) {
            Storage::disk('public')->delete($oldPath);
        }

        Setting::updateOrCreate(
            ['key' => 'store.logo'],
            ['value' => '', 'group' => 'store', 'is_public' => true]
        );

        return $this->success(null, 'Logo removed successfully.');
    }

    /**
     * GET /api/v1/stats — public store statistics (product/category counts).
     */
    public function stats(): JsonResponse
    {
        return $this->success([
            'active_products' => \App\Models\Product::active()->count(),
            'active_categories' => \App\Models\Category::active()->count(),
            'total_reviews' => \App\Models\Review::count(),
        ], 'Store statistics retrieved successfully.');
    }

    /**
     * GET /api/v1/admin/dashboard/revenue-trend — daily revenue for paid
     * orders over the last N days (default 30). Admin only.
     *
     * Query params:
     *   days — number of days to look back (7, 14, 30, 60, 90)
     */
    public function revenueTrend(Request $request): JsonResponse
    {
        $days = min(max((int) $request->query('days', 30), 1), 90);

        $startDate = now()->subDays($days)->startOfDay();
        $endDate = now()->endOfDay();

        // Group paid orders by date, sum their total.
        $rows = DB::table('orders')
            ->select(
                DB::raw('DATE(placed_at) as date'),
                DB::raw('COALESCE(SUM(total), 0) as revenue'),
                DB::raw('COUNT(*) as order_count')
            )
            ->where('payment_status', 'paid')
            ->whereNotNull('placed_at')
            ->where('placed_at', '>=', $startDate)
            ->where('placed_at', '<=', $endDate)
            ->groupBy(DB::raw('DATE(placed_at)'))
            ->orderBy('date')
            ->get();

        // Fill in missing dates with zero revenue so charts draw a continuous line.
        $map = $rows->keyBy('date');
        $data = [];
        for ($d = $startDate->copy(); $d <= $endDate; $d->addDay()) {
            $key = $d->format('Y-m-d');
            $row = $map->get($key);
            $data[] = [
                'date' => $key,
                'revenue' => $row ? (float) $row->revenue : 0,
                'order_count' => $row ? (int) $row->order_count : 0,
            ];
        }

        return $this->success([
            'days' => $days,
            'data' => $data,
            'total_revenue' => $data ? number_format(array_sum(array_column($data, 'revenue')), 2, '.', '') : '0.00',
            'total_orders' => array_sum(array_column($data, 'order_count')),
        ], 'Revenue trend retrieved successfully.');
    }
}