<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewResource extends JsonResource
{
    /**
     * Transform a review into its customer-facing API representation.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'user_id' => $this->user_id,
            'order_id' => $this->order_id,
            'rating' => $this->rating,
            'title' => $this->when($this->title !== null, $this->title),
            'comment' => $this->when($this->comment !== null, $this->comment),
            'status' => $this->status,
            'created_at' => $this->created_at,
        ];
    }
}