<?php

namespace App\Services;

use App\Models\ShippingMethod;
use Illuminate\Validation\ValidationException;

/**
 * Shipping method lookup and pricing.
 *
 * All shipping figures are computed here, server-side, from the database.
 * A method is priced from its base_rate unless the cart subtotal reaches its
 * free_over threshold. When no method is configured yet the legacy flat rate
 * from config('store.shipping_fee') keeps the store operational.
 */
class ShippingMethodService
{
    /**
     * The shipping fee for a method given an order subtotal.
     *
     * @return float the fee in store currency
     */
    public function calculate(?ShippingMethod $method, float $subtotal): float
    {
        if ($method === null) {
            return round((float) config('store.shipping_fee', 0), 2);
        }

        $freeOver = $method->free_over !== null ? (float) $method->free_over : null;

        if ($freeOver !== null && $subtotal >= $freeOver) {
            return 0.0;
        }

        return round((float) $method->base_rate, 2);
    }

    /**
     * The shipping method customers see selected first at checkout:
     * the single default method, otherwise the first active one by sort order.
     */
    public function defaultMethod(): ?ShippingMethod
    {
        return ShippingMethod::query()
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();
    }

    /**
     * Resolve a payload shipping_method_id to an orderable method.
     *
     * Returns null when no id was supplied (the caller falls back to the
     * default method) and throws a ValidationException when an id was supplied
     * but the method is missing or unavailable.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function resolveForOrder(?int $shippingMethodId): ?ShippingMethod
    {
        if ($shippingMethodId === null) {
            return $this->defaultMethod();
        }

        $method = ShippingMethod::query()->find($shippingMethodId);

        if (! $method || ! $method->isAvailable()) {
            throw ValidationException::withMessages([
                'shipping_method_id' => 'The selected shipping method is not available.',
            ]);
        }

        return $method;
    }
}