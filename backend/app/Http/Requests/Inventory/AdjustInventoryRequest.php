<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdjustInventoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')],
            'type' => ['sometimes', Rule::in(['adjustment', 'purchase'])],
            'quantity_change' => ['required', 'integer', 'not_in:0'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ((int) $this->input('quantity_change') < 0 && $this->input('type', 'adjustment') === 'purchase') {
                $validator->errors()->add('type', 'Purchases must be positive; use adjustment for stock that decreases.');
            }
        });
    }
}