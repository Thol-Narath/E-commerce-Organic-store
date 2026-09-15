<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'contact_person' => $this->contact_person,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'city' => $this->city,
            'country' => $this->country,
            'tax_id' => $this->tax_id,
            'notes' => $this->notes,
            'is_active' => (bool) $this->is_active,
            'orders_count' => $this->when(isset($this->orders_count), (int) $this->orders_count),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}