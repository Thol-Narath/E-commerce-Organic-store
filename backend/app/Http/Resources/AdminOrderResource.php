<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\FormatsMoney;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Full order representation for the admin order dashboard (Phase 9).
 *
 * Historical accuracy is guaranteed by using only stored snapshots: items carry
 * product name/SKU/price captured at placement time and `shipping_address` is
 * the frozen snapshot. Totals are the stored server-computed values — they are
 * never recomputed from current prices.
 *
 * Payment records come from the attempt history; the resource exposes gateway
 * transaction id/reference (admin-only) but no card data or secrets.
 */
class AdminOrderResource extends JsonResource
{
    use FormatsMoney;

    public function toArray(Request $request): array
    {
        $snapshot = $this->shipping_address_snapshot
            ? json_decode($this->shipping_address_snapshot, true)
            : null;

        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'status' => $this->status,
            'payment_status' => $this->payment_status,
            'customer' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
                'phone' => $this->user->phone,
            ]),
            'subtotal' => $this->money($this->subtotal),
            'discount' => $this->money($this->discount),
            'shipping_fee' => $this->money($this->shipping_fee),
            'tax' => $this->money($this->tax),
            'total' => $this->money($this->total),
            'shipping_address' => $snapshot,
            'notes' => $this->notes,
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'payments' => PaymentResource::collection($this->whenLoaded('payments')),
            'status_history' => OrderStatusHistoryResource::collection($this->whenLoaded('statusHistories')),
            'admin_notes' => OrderNoteResource::collection($this->whenLoaded('adminNotes')),
            'placed_at' => $this->when($this->placed_at !== null, $this->placed_at?->toISOString()),
            'created_at' => $this->when($this->created_at !== null, $this->created_at?->toISOString()),
            'updated_at' => $this->when($this->updated_at !== null, $this->updated_at?->toISOString()),
        ];
    }
}