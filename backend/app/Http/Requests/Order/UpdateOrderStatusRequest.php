<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validate an admin/staff order status update (Phase 9).
 *
 * Cancellation is intentionally excluded from the `status` whitelist — it is
 * performed through the dedicated POST …/cancel endpoint. The request whitelists
 * statuses only; the transition legality is enforced by AdminOrderService so the
 * order lifecycle can never be jumped arbitrarily.
 */
class UpdateOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['pending', 'confirmed', 'processing', 'packed', 'shipped', 'out_for_delivery', 'delivered'])],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}