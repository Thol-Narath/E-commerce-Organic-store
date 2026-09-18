<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ShippingMethod\StoreShippingMethodRequest;
use App\Http\Requests\ShippingMethod\UpdateShippingMethodRequest;
use App\Http\Resources\ShippingMethodResource;
use App\Models\ShippingMethod;
use App\Services\CacheService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminShippingMethodController extends Controller
{
    use ApiResponse, Paginates;

    public function __construct(private readonly CacheService $cacheService) {}

    /**
     * GET /api/v1/admin/shipping-methods — paginated shipping method list with
     * search + status filter.
     */
    public function index(Request $request): JsonResponse
    {
        $query = ShippingMethod::query()->orderBy('sort_order')->orderBy('id');

        if ($request->filled('search')) {
            $term = $request->input('search');
            $query->where(function (Builder $q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('code', 'like', "%{$term}%");
            });
        }

        if ($request->filled('status')) {
            if ($request->input('status') === 'active') {
                $query->where('is_active', true);
            } elseif ($request->input('status') === 'inactive') {
                $query->where('is_active', false);
            }
        }

        $paginator = $query->paginate($this->perPage($request, 15))->withQueryString();

        return $this->success([
            'items' => ShippingMethodResource::collection($paginator->items()),
            'pagination' => $this->pagination($paginator),
        ], 'Shipping methods retrieved successfully.');
    }

    /**
     * GET /api/v1/admin/shipping-methods/{shipping_method} — single method.
     */
    public function show(ShippingMethod $shippingMethod): JsonResponse
    {
        return $this->success(
            new ShippingMethodResource($shippingMethod),
            'Shipping method retrieved successfully.'
        );
    }

    /**
     * POST /api/v1/admin/shipping-methods — create a shipping method. Only one
     * method may be the default; assigning a default clears the others.
     */
    public function store(StoreShippingMethodRequest $request): JsonResponse
    {
        $shippingMethod = DB::transaction(function () use ($request) {
            if ($request->boolean('is_default')) {
                ShippingMethod::query()->where('is_default', true)->update(['is_default' => false]);
            }

            return ShippingMethod::create($request->validated());
        });

        $this->cacheService->invalidate('settings');

        return $this->success(
            new ShippingMethodResource($shippingMethod),
            'Shipping method created successfully.',
            201
        );
    }

    /**
     * PUT /api/v1/admin/shipping-methods/{shipping_method} — update a method.
     */
    public function update(UpdateShippingMethodRequest $request, ShippingMethod $shippingMethod): JsonResponse
    {
        $method = DB::transaction(function () use ($request, $shippingMethod) {
            if ($request->boolean('is_default') && ! $shippingMethod->is_default) {
                ShippingMethod::query()->where('is_default', true)->update(['is_default' => false]);
            }

            $shippingMethod->update($request->validated());

            return $shippingMethod;
        });

        $this->cacheService->invalidate('settings');

        return $this->success(
            new ShippingMethodResource($method->fresh()),
            'Shipping method updated successfully.'
        );
    }

    /**
     * DELETE /api/v1/admin/shipping-methods/{shipping_method} — soft-delete a
     * method. Existing orders keep their fee and name snapshots.
     */
    public function destroy(ShippingMethod $shippingMethod): JsonResponse
    {
        $shippingMethod->delete();
        $this->cacheService->invalidate('settings');

        return $this->success(null, 'Shipping method deleted successfully.');
    }

    /**
     * PATCH /api/v1/admin/shipping-methods/{shipping_method}/toggle — quick
     * active/inactive switch for gatekeeping methods at checkout.
     */
    public function toggle(ShippingMethod $shippingMethod): JsonResponse
    {
        $shippingMethod->update(['is_active' => ! $shippingMethod->is_active]);
        $this->cacheService->invalidate('settings');

        return $this->success(
            new ShippingMethodResource($shippingMethod->fresh()),
            'Shipping method status updated successfully.'
        );
    }

    /**
     * PATCH /api/v1/admin/shipping-methods/{shipping_method}/default — make a
     * method the preselected option at checkout. The previous default is
     * cleared automatically.
     */
    public function setDefault(ShippingMethod $shippingMethod): JsonResponse
    {
        $method = DB::transaction(function () use ($shippingMethod) {
            ShippingMethod::query()->where('is_default', true)->update(['is_default' => false]);
            $shippingMethod->update(['is_default' => true]);

            return $shippingMethod;
        });

        $this->cacheService->invalidate('settings');

        return $this->success(
            new ShippingMethodResource($method->fresh()),
            'Default shipping method updated successfully.'
        );
    }
}