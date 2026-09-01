<?php

namespace App\Http\Resources;

use App\Services\InventoryService;
use Illuminate\Http\Request;

class InventoryItemResource extends AdminProductResource
{
    /**
     * Product with stock overview for the admin/staff inventory screens:
     * extends AdminProductResource with availability and low-stock signals.
     */
    public function toArray(Request $request): array
    {
        $data = parent::toArray($request);

        $data['is_low_stock'] = $this->isLowStock();
        $data['available'] = $this->isAvailable() && $this->isInStock();
        $data['stock_status'] = InventoryService::stockStatusFor((int) $this->stock_quantity, (int) $this->low_stock_threshold);
        $data['reorder_level'] = (int) $this->low_stock_threshold;

        $last = $this->relationLoaded('lastInventoryTransaction') ? $this->lastInventoryTransaction : null;
        $data['last_inventory_transaction_at'] = $last?->created_at;

        return $data;
    }
}