<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Concerns\FormatsMoney;

class OrderResource extends JsonResource
{
    use FormatsMoney;

    /**
     * Transform an order. Totals come from the stored (server-calculated)
     * values; the shipping address is the snapshot taken at placement time.
     *
     * NOTE: `whenLoaded` must never receive a Closure value — it returns the
     * value verbatim, so the latest payment is resolved here eagerly.
     */
    public function toArray(Request $request): array
    {
        $snapshot = $this->shipping_address_snapshot
            ? json_decode($this->shipping_address_snapshot, true)
            : null;

        $latestPayment = $this->payments->sortByDesc('id')->first();

        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'status' => $this->status,
            'payment_status' => $this->payment_status,
            'subtotal' => $this->money($this->subtotal),
            'discount' => $this->money($this->discount),
            'shipping_fee' => $this->money($this->shipping_fee),
            'tax' => $this->money($this->tax),
            'total' => $this->money($this->total),
            'shipping_address' => $snapshot,
            'notes' => $this->notes,
            'placed_at' => $this->when($this->placed_at !== null, $this->placed_at?->toISOString()),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'payment' => $this->whenLoaded(
                'payments',
                $latestPayment ? new PaymentResource($latestPayment) : null
            ),
            'status_histories' => \App\Http\Resources\OrderStatusHistoryResource::collection($this->whenLoaded('statusHistories')),
            'created_at' => $this->when($this->created_at !== null, $this->created_at->toISOString()),
        ];
    }
}