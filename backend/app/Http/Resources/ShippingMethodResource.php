<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\FormatsMoney;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShippingMethodResource extends JsonResource
{
    use FormatsMoney;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'base_rate' => $this->money($this->base_rate),
            'free_over' => $this->free_over === null ? null : $this->money($this->free_over),
            'estimated_days' => $this->estimated_days,
            'is_active' => (bool) $this->is_active,
            'is_default' => (bool) $this->is_default,
            'sort_order' => (int) $this->sort_order,
            'created_at' => $this->when(isset($this->created_at), $this->created_at?->toISOString()),
            'updated_at' => $this->when(isset($this->updated_at), $this->updated_at?->toISOString()),
        ];
    }
}