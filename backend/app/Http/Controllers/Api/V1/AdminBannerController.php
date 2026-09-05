<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\BannerResource;
use App\Models\Banner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AdminBannerController extends Controller
{
    use ApiResponse, Paginates;

    /**
     * GET /api/v1/admin/banners — paginated list of all banners (admin view, includes inactive).
     */
    public function index(Request $request): JsonResponse
    {
        $paginator = Banner::query()
            ->orderBy('sort_order')
            ->orderBy('title')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return $this->success([
            'items' => BannerResource::collection($paginator->items()),
            'pagination' => $this->pagination($paginator),
        ], 'Banners retrieved successfully.');
    }

    /**
     * GET /api/v1/admin/banners/{banner} — show a single banner.
     */
    public function show(Banner $banner): JsonResponse
    {
        return $this->success(
            new BannerResource($banner),
            'Banner retrieved successfully.'
        );
    }

    /**
     * POST /api/v1/admin/banners — create a new banner.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'subtitle' => 'nullable|string|max:500',
            'discount_percent' => 'nullable|integer|min:0|max:100',
            'discount_label' => 'nullable|string|max:100',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:20480',
            'link_url' => 'nullable|string|max:500',
            'bg_color' => 'nullable|string|max:7',
            'cta_text' => 'nullable|string|max:100',
            'cta_link' => 'nullable|string|max:500',
            'is_active' => 'sometimes|nullable|in:true,false,1,0',
            'sort_order' => 'sometimes|nullable|integer|min:0',
        ]);

        // Cast string booleans and numeric strings to proper types —
        // FormData always sends everything as strings.
        if (isset($validated['is_active'])) {
            $validated['is_active'] = in_array($validated['is_active'], ['true', '1', true, 1], true);
        }
        if (isset($validated['sort_order'])) {
            $validated['sort_order'] = (int) $validated['sort_order'];
        }

        if ($request->hasFile('image')) {
            $validated['image_url'] = $request->file('image')->store('banners', 'public');
        }

        unset($validated['image']);

        $validated['is_active'] = $validated['is_active'] ?? true;
        $validated['sort_order'] = $validated['sort_order'] ?? (Banner::max('sort_order') + 1);

        $banner = Banner::create($validated);

        return $this->success(
            new BannerResource($banner),
            'Banner created successfully.',
            201
        );
    }

    /**
     * PUT /api/v1/admin/banners/{banner} — update an existing banner.
     */
    public function update(Request $request, Banner $banner): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'subtitle' => 'nullable|string|max:500',
            'discount_percent' => 'nullable|integer|min:0|max:100',
            'discount_label' => 'nullable|string|max:100',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:20480',
            'link_url' => 'nullable|string|max:500',
            'bg_color' => 'nullable|string|max:7',
            'cta_text' => 'nullable|string|max:100',
            'cta_link' => 'nullable|string|max:500',
            'is_active' => 'sometimes|nullable|in:true,false,1,0',
            'sort_order' => 'sometimes|nullable|integer|min:0',
        ]);

        // Cast string booleans and numeric strings to proper types —
        // FormData always sends everything as strings.
        if (isset($validated['is_active'])) {
            $validated['is_active'] = in_array($validated['is_active'], ['true', '1', true, 1], true);
        }
        if (isset($validated['sort_order'])) {
            $validated['sort_order'] = (int) $validated['sort_order'];
        }

        if ($request->hasFile('image')) {
            if ($banner->image_url) {
                Storage::disk('public')->delete($banner->image_url);
            }
            $validated['image_url'] = $request->file('image')->store('banners', 'public');
        }

        unset($validated['image']);

        $banner->update($validated);

        return $this->success(
            new BannerResource($banner->fresh()),
            'Banner updated successfully.'
        );
    }

    /**
     * DELETE /api/v1/admin/banners/{banner} — delete a banner.
     */
    public function destroy(Banner $banner): JsonResponse
    {
        if ($banner->image_url) {
            Storage::disk('public')->delete($banner->image_url);
        }

        $banner->delete();

        return $this->success(null, 'Banner deleted successfully.');
    }

    /**
     * PATCH /api/v1/admin/banners/{banner}/toggle — quick active/inactive toggle.
     */
    public function toggle(Banner $banner): JsonResponse
    {
        $banner->update(['is_active' => !$banner->is_active]);

        return $this->success(
            new BannerResource($banner->fresh()),
            'Banner status updated successfully.'
        );
    }

    /**
     * POST /api/v1/admin/banners/reorder — update sort_order for multiple banners.
     * Expects { orders: [{ id, sort_order }, ...] }.
     */
    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'orders' => 'required|array',
            'orders.*.id' => 'required|exists:banners,id',
            'orders.*.sort_order' => 'required|integer|min:0',
        ]);

        foreach ($validated['orders'] as $item) {
            Banner::where('id', $item['id'])->update(['sort_order' => $item['sort_order']]);
        }

        $banners = Banner::query()
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get();

        return $this->success(
            BannerResource::collection($banners),
            'Banner order updated successfully.'
        );
    }
}
