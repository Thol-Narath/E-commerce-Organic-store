<?php

namespace App\Http\Requests\Cart;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCartItemRequest extends FormRequest
{
    /**
     * Any authenticated customer may update their own cart items.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Shape validation only. Ownership of the cart item is verified by the
     * controller via the CartService; availability/stock limits are enforced
     * server-side by the CartService.
     */
    public function rules(): array
    {
        return [
            'quantity' => ['required', 'integer', 'min:1'],
        ];
    }
}