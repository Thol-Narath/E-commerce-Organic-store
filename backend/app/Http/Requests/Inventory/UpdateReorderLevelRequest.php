<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Configure a product's low-stock / reorder threshold. Pure configuration —
 * never touches stock or the ledger.
 */
class UpdateReorderLevelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reorder_level' => ['required', 'integer', 'min:0'],
        ];
    }
}