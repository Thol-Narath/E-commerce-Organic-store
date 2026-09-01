<?php

namespace App\Http\Requests\Checkout;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutRequest extends FormRequest
{
    /**
     * Any authenticated customer may place an order.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Shape validation only: the address must exist. Address ownership is
     * verified by the OrderService (404 for another customer's address),
     * cart emptiness and stock/availability are enforced server-side.
     *
     * Order totals are NEVER accepted from the client — the OrderService
     * calculates every figure from live product prices.
     */
    public function rules(): array
    {
        return [
            'address_id' => ['required', 'integer', Rule::exists('addresses', 'id')],
        ];
    }
}