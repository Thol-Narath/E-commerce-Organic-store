<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Remove stock from a product (ledger type: adjustment). A reason is required
 * so the audit trail always answers "why". Quantity may never exceed available
 * stock — enforced in InventoryService (no negative stock).
 */
class RemoveStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'quantity' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }
}