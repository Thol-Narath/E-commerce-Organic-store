<?php

namespace App\Http\Requests\Cart;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddToCartRequest extends FormRequest
{
    /**
     * Any authenticated customer may add to their own cart.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Shape validation only. Product availability, stock limits and
     * min-order-quantity are enforced by the CartService.
     */
    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')],
            'quantity' => ['required', 'integer', 'min:1'],
        ];
    }
}