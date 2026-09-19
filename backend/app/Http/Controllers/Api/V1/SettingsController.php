<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Mail\TestMail;
use App\Models\Category;
use App\Models\Product;
use App\Models\Review;
use App\Models\Setting;
use App\Services\CacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class SettingsController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly CacheService $cacheService) {}

    /**
     * GET /api/v1/settings/public — public store information used by the
     * customer frontend (display only). Reads key/value pairs from the
     * settings table where is_public = true and augments them with
     * computed URLs (e.g. hero banner).
     */
    public function publicSettings(): JsonResponse
    {
        $data = $this->cacheService->remember(
            'settings',
            'public',
            CacheService::TTL_LONG,
            function () {
                $rows = Setting::where('is_public', true)
                    ->get()
                    ->keyBy('key');

                $heroUrl = $rows->get('hero_banner.url')->value ?? null;
                $logoPath = $rows->get('store.logo')->value ?? null;
                $aboutPath = $rows->get('about.image')->value ?? null;

                if ($heroUrl && ! str_starts_with($heroUrl, 'http')) {
                    $heroUrl = url('storage/'.ltrim($heroUrl, '/'));
                }

                $logoUrl = null;
                if ($logoPath) {
                    $logoUrl = str_starts_with($logoPath, 'http')
                        ? $logoPath
                        : url('storage/'.ltrim($logoPath, '/'));
                }

                $aboutUrl = null;
                if ($aboutPath) {
                    $aboutUrl = str_starts_with($aboutPath, 'http')
                        ? $aboutPath
                        : url('storage/'.ltrim($aboutPath, '/'));
                }

                return [
                    'store' => [
                        'name' => $rows->get('store.name')->value ?? config('app.name', 'Organic Store'),
                        'tagline' => $rows->get('store.tagline')->value ?? '',
                        'currency' => $rows->get('store.currency')->value ?? 'USD',
                        'currency_symbol' => $rows->get('general.currency_symbol')->value ?? '$',
                        'logo_height' => (int) ($rows->get('store.logo_height')->value ?? 42),
                        'logo' => $logoUrl,
                    ],
                    'contact' => [
                        'address' => $rows->get('store.contact_address')->value ?? '',
                        'phone' => $rows->get('store.contact_phone')->value ?? '',
                        'email' => $rows->get('store.contact_email')->value ?? '',
                    ],
                    'shipping' => [
                        'flat_rate' => number_format((float) ($rows->get('shipping.flat_rate')->value ?? 0), 2, '.', ''),
                        'free_over' => number_format((float) ($rows->get('shipping.free_over')->value ?? 0), 2, '.', ''),
                    ],
                    'hero_banner_url' => $heroUrl,
                    'about' => [
                        'image_url' => $aboutUrl,
                    ],
                    'location' => [
                        'name' => $rows->get('store.name')->value ?? config('app.name', 'Organic Store'),
                        'address' => $rows->get('store.contact_address')->value ?? '',
                        'phone' => $rows->get('store.contact_phone')->value ?? '',
                        'email' => $rows->get('store.contact_email')->value ?? '',
                        'latitude' => $rows->get('store.latitude')->value ?? null,
                        'longitude' => $rows->get('store.longitude')->value ?? null,
                        'google_maps_url' => $rows->get('store.google_maps_url')->value ?? null,
                        'google_maps_embed_url' => $rows->get('store.google_maps_embed_url')->value ?? null,
                        'business_hours' => $rows->get('store.business_hours')->value ?? '',
                    ],
                ];
            }
        );

        return $this->success($data, 'Settings retrieved successfully.');
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

        $this->cacheService->invalidate('settings');

        return $this->success([
            'name' => $values['store.name'],
            'tagline' => $values['store.tagline'],
            'logo_height' => (int) $values['store.logo_height'],
        ], 'Store branding updated successfully.');
    }

    /**
     * PUT /api/v1/admin/settings/contact — update the store contact details
     * (address, phone, email) shown in the storefront footer (admin only).
     */
    public function updateContact(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:60'],
            'email' => ['nullable', 'email', 'max:120'],
        ]);

        $values = [
            'store.contact_address' => trim($validated['address'] ?? ''),
            'store.contact_phone' => trim($validated['phone'] ?? ''),
            'store.contact_email' => trim($validated['email'] ?? ''),
        ];

        foreach ($values as $key => $value) {
            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => $value, 'group' => 'store', 'is_public' => true]
            );
        }

        $this->cacheService->invalidate('settings');

        return $this->success([
            'address' => $values['store.contact_address'],
            'phone' => $values['store.contact_phone'],
            'email' => $values['store.contact_email'],
        ], 'Contact information updated successfully.');
    }

    /**
     * POST /api/v1/admin/settings/test-email — verify email delivery by sending
     * a real test message (admin only). Rate-limited to avoid spam.
     *
     * Body:
     *   email — optional recipient; defaults to the configured mail from address.
     */
    public function sendTestEmail(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['nullable', 'email', 'max:190'],
        ]);

        $recipient = trim((string) ($validated['email'] ?? '')
            ?: config('mail.from.address')
            ?: $request->user()->email);

        // Catch the most common misconfiguration up-front: the placeholder
        // password shipped in .env.example was never replaced. SMTP servers
        // answer with an opaque 535 error, so explain the fix instead.
        $smtpPassword = (string) config('mail.mailers.smtp.password');

        if ($smtpPassword === 'REPLACE_WITH_YOUR_GMAIL_APP_PASSWORD') {
            return $this->error(
                'No real email password is set. Open backend/.env, replace MAIL_PASSWORD with a Gmail App Password generated at https://myaccount.google.com/apppasswords (2-Step Verification must be enabled), then restart the server.',
                null,
                422
            );
        }

        $storeName = Setting::where('key', 'store.name')->value('value')
            ?: config('app.name', 'Organic Store');

        try {
            Mail::to($recipient)->send(new TestMail($storeName));
        } catch (\Throwable $e) {
            Log::error('Test email failed to send', [
                'recipient' => $recipient,
                'error' => $e->getMessage(),
            ]);

            return $this->error('Test email could not be sent. Check your MAIL_* settings in backend/.env: '.$e->getMessage(), null, 502);
        }

        return $this->success(
            ['email' => $recipient],
            "Test email sent to {$recipient}. Check your inbox."
        );
    }

    /**
     * GET /api/v1/admin/settings/logo — current store logo (admin only).
     */
    public function getLogo(): JsonResponse
    {
        $path = Setting::where('key', 'store.logo')->value('value');

        return $this->success([
            'logo' => $path ? url('storage/'.ltrim($path, '/')) : null,
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
        if ($oldPath && ! str_starts_with($oldPath, 'http')) {
            Storage::disk('public')->delete($oldPath);
        }

        $path = $file->store('logos', 'public');

        Setting::updateOrCreate(
            ['key' => 'store.logo'],
            ['value' => $path, 'group' => 'store', 'is_public' => true]
        );

        $this->cacheService->invalidate('settings');

        return $this->success(
            ['logo' => url('storage/'.ltrim($path, '/')), 'is_set' => true],
            'Logo updated successfully.'
        );
    }

    /**
     * DELETE /api/v1/admin/settings/logo — remove the store logo (admin only).
     */
    public function removeLogo(): JsonResponse
    {
        $oldPath = Setting::where('key', 'store.logo')->value('value');
        if ($oldPath && ! str_starts_with($oldPath, 'http')) {
            Storage::disk('public')->delete($oldPath);
        }

        Setting::updateOrCreate(
            ['key' => 'store.logo'],
            ['value' => '', 'group' => 'store', 'is_public' => true]
        );

        $this->cacheService->invalidate('settings');

        return $this->success(null, 'Logo removed successfully.');
    }

    /**
     * GET /api/v1/admin/settings/about-image — current about section image
     * shown on the storefront homepage (admin only).
     */
    public function getAboutImage(): JsonResponse
    {
        $path = Setting::where('key', 'about.image')->value('value');

        return $this->success([
            'image_url' => $path && ! str_starts_with($path, 'http')
                ? url('storage/'.ltrim($path, '/'))
                : ($path ?: null),
            'is_set' => (bool) $path,
        ], 'About image retrieved successfully.');
    }

    /**
     * POST /api/v1/admin/settings/about-image — upload/replace the about
     * section image used by the storefront homepage (admin only).
     * Expects a multipart field `about_image` (png, jpg, jpeg or webp).
     */
    public function uploadAboutImage(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'about_image' => 'required|image|mimes:png,jpg,jpeg,webp|max:4096',
        ]);

        $file = $validated['about_image'];

        // Remove the previous about image file if it lives on the public disk.
        $oldPath = Setting::where('key', 'about.image')->value('value');
        if ($oldPath && ! str_starts_with($oldPath, 'http')) {
            Storage::disk('public')->delete($oldPath);
        }

        $path = $file->store('about', 'public');

        Setting::updateOrCreate(
            ['key' => 'about.image'],
            ['value' => $path, 'group' => 'about', 'is_public' => true]
        );

        return $this->success(
            ['image_url' => url('storage/'.ltrim($path, '/')), 'is_set' => true],
            'About image updated successfully.'
        );
    }

    /**
     * DELETE /api/v1/admin/settings/about-image — remove the about section
     * image (admin only). The storefront falls back to default slides.
     */
    public function removeAboutImage(): JsonResponse
    {
        $oldPath = Setting::where('key', 'about.image')->value('value');
        if ($oldPath && ! str_starts_with($oldPath, 'http')) {
            Storage::disk('public')->delete($oldPath);
        }

        Setting::updateOrCreate(
            ['key' => 'about.image'],
            ['value' => '', 'group' => 'about', 'is_public' => true]
        );

        $this->cacheService->invalidate('settings');

        return $this->success(null, 'About image removed successfully.');
    }

    /**
     * GET /api/v1/admin/settings/store-location — current store location,
     * contact and map configuration used by the About page (admin only).
     */
    public function getStoreLocation(): JsonResponse
    {
        $keys = [
            'store.name',
            'store.contact_address',
            'store.contact_phone',
            'store.contact_email',
            'store.latitude',
            'store.longitude',
            'store.google_maps_url',
            'store.google_maps_embed_url',
            'store.business_hours',
        ];

        $rows = Setting::whereIn('key', $keys)->get()->keyBy('key');

        return $this->success([
            'store_name' => $rows->get('store.name')->value ?? '',
            'address' => $rows->get('store.contact_address')->value ?? '',
            'phone' => $rows->get('store.contact_phone')->value ?? '',
            'email' => $rows->get('store.contact_email')->value ?? '',
            'latitude' => $rows->get('store.latitude')->value ?? null,
            'longitude' => $rows->get('store.longitude')->value ?? null,
            'google_maps_url' => $rows->get('store.google_maps_url')->value ?? null,
            'google_maps_embed_url' => $rows->get('store.google_maps_embed_url')->value ?? null,
            'business_hours' => $rows->get('store.business_hours')->value ?? '',
        ], 'Store location retrieved successfully.');
    }

    /**
     * PUT /api/v1/admin/settings/store-location — update the store location,
     * contact and Google Map configuration shown on the About page (admin only).
     */
    public function updateStoreLocation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'store_name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'google_maps_url' => ['nullable', 'url', 'max:500'],
            'google_maps_embed_url' => ['nullable', 'string', 'max:500'],
            'business_hours' => ['nullable', 'string', 'max:500'],
        ]);

        $values = [
            'store.name' => trim($validated['store_name']),
            'store.contact_address' => trim($validated['address'] ?? ''),
            'store.contact_phone' => trim($validated['phone'] ?? ''),
            'store.contact_email' => trim($validated['email'] ?? ''),
            'store.latitude' => ($validated['latitude'] ?? '') === '' ? '' : (string) $validated['latitude'],
            'store.longitude' => ($validated['longitude'] ?? '') === '' ? '' : (string) $validated['longitude'],
            'store.google_maps_url' => trim($validated['google_maps_url'] ?? ''),
            'store.google_maps_embed_url' => trim($validated['google_maps_embed_url'] ?? ''),
            'store.business_hours' => trim($validated['business_hours'] ?? ''),
        ];

        foreach ($values as $key => $value) {
            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => (string) $value, 'group' => 'store', 'is_public' => true]
            );
        }

        $this->cacheService->invalidate('settings');

        return $this->success([
            'store_name' => $values['store.name'],
            'address' => $values['store.contact_address'],
            'phone' => $values['store.contact_phone'],
            'email' => $values['store.contact_email'],
            'latitude' => $values['store.latitude'],
            'longitude' => $values['store.longitude'],
            'google_maps_url' => $values['store.google_maps_url'],
            'google_maps_embed_url' => $values['store.google_maps_embed_url'],
            'business_hours' => $values['store.business_hours'],
        ], 'Store location updated successfully.');
    }

    /**
     * GET /api/v1/stats — public store statistics (product/category counts).
     */
    public function stats(): JsonResponse
    {
        $data = $this->cacheService->remember(
            'stats',
            'public',
            CacheService::TTL_MEDIUM,
            function () {
                return [
                    'active_products' => Product::active()->count(),
                    'active_categories' => Category::active()->count(),
                    'total_reviews' => Review::count(),
                ];
            }
        );

        return $this->success($data, 'Store statistics retrieved successfully.');
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

        $data = $this->cacheService->remember(
            'revenue',
            ['trend', 'days' => $days],
            CacheService::TTL_SHORT,
            function () use ($days) {
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

                return [
                    'days' => $days,
                    'data' => $data,
                    'total_revenue' => $data ? number_format(array_sum(array_column($data, 'revenue')), 2, '.', '') : '0.00',
                    'total_orders' => array_sum(array_column($data, 'order_count')),
                ];
            }
        );

        return $this->success($data, 'Revenue trend retrieved successfully.');
    }
}
