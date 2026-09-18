<?php

namespace App\Http\Requests\ShippingMethod;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateShippingMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'code' => [
                'sometimes',
                'required',
                'alpha_dash',
                'max:50',
                Rule::unique('shipping_methods', 'code')->ignore($this->route('shipping_method')),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'base_rate' => ['sometimes', 'required', 'numeric', 'min:0'],
            'free_over' => ['nullable', 'numeric', 'min:0'],
            'estimated_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'is_active' => ['sometimes', 'boolean'],
            'is_default' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}