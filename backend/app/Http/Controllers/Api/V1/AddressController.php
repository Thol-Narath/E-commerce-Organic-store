<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Address\StoreAddressRequest;
use App\Http\Requests\Address\UpdateAddressRequest;
use App\Http\Resources\AddressResource;
use App\Services\AddressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AddressController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly AddressService $addressService) {}

    /**
     * GET /api/v1/addresses — the authenticated customer's address book.
     */
    public function index(Request $request): JsonResponse
    {
        return $this->success(
            AddressResource::collection($this->addressService->list($request->user())),
            'Addresses retrieved successfully.'
        );
    }

    /**
     * POST /api/v1/addresses — add an address (first one becomes default).
     */
    public function store(StoreAddressRequest $request): JsonResponse
    {
        $address = $this->addressService->store($request->user(), $request->validated());

        return $this->success(new AddressResource($address), 'Address added successfully.', 201);
    }

    /**
     * GET /api/v1/addresses/{address} — a single address (own only, 404 otherwise).
     */
    public function show(Request $request, int $address): JsonResponse
    {
        $model = $request->user()->addresses()->find($address);

        if (! $model) {
            return $this->error('Address not found.', null, 404);
        }

        return $this->success(new AddressResource($model), 'Address retrieved successfully.');
    }

    /**
     * PATCH /api/v1/addresses/{address} — update an address (own only).
     */
    public function update(UpdateAddressRequest $request, int $address): JsonResponse
    {
        $model = $this->addressService->update($request->user(), $address, $request->validated());

        if (! $model) {
            return $this->error('Address not found.', null, 404);
        }

        return $this->success(new AddressResource($model), 'Address updated successfully.');
    }

    /**
     * DELETE /api/v1/addresses/{address} — remove an address (own only).
     */
    public function destroy(Request $request, int $address): JsonResponse
    {
        $result = $this->addressService->destroy($request->user(), $address);

        if ($result === 'not_found') {
            return $this->error('Address not found.', null, 404);
        }

        if ($result === 'in_use') {
            return $this->error('This address is attached to existing orders and cannot be deleted.', null, 409);
        }

        return $this->success(null, 'Address deleted successfully.');
    }

    /**
     * PATCH /api/v1/addresses/{address}/default — set the default shipping address.
     */
    public function setDefault(Request $request, int $address): JsonResponse
    {
        $model = $this->addressService->setDefault($request->user(), $address);

        if (! $model) {
            return $this->error('Address not found.', null, 404);
        }

        return $this->success(new AddressResource($model), 'Default address updated.');
    }
}