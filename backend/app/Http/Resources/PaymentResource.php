<?php

namespace App\Http\Resources;

use App\Enums\PaymentMethod;
use App\Http\Resources\Concerns\FormatsMoney;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;

class PaymentResource extends JsonResource
{
    use FormatsMoney;

    /**
     * Transform a payment for the client.
     *
     * Sensitive gateway data (hosted checkout HTML) stays server-side; the
     * client only receives an expiring signed checkout URL for card payments.
     * QR/deeplink data is only exposed while the attempt is still pending.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payment_number' => $this->payment_number,
            'order_number' => $this->whenLoaded('order', fn () => $this->order->order_number),
            'payment_method' => $this->payment_method,
            'payment_method_label' => PaymentMethod::tryFrom($this->payment_method)?->label(),
            'gateway' => $this->gateway,
            'payment_status' => $this->payment_status,
            'amount' => $this->money($this->amount),
            'currency' => $this->currency,
            'qr_string' => $this->when($this->payment_status === 'pending' && $this->qr_string !== null, $this->qr_string),
            'deeplink' => $this->when($this->payment_status === 'pending' && $this->deeplink !== null, $this->deeplink),
            'checkout_url' => $this->when($this->shouldOfferCheckout(), $this->checkoutUrl()),
            'expires_at' => $this->when($this->expires_at !== null, $this->expires_at?->toISOString()),
            'paid_at' => $this->when($this->paid_at !== null, $this->paid_at?->toISOString()),
            'created_at' => $this->when($this->created_at !== null, $this->created_at?->toISOString()),
            'gateway_reference' => $this->when($this->gateway_reference !== null, $this->gateway_reference),
            'gateway_transaction_id' => $this->when($this->gateway_transaction_id !== null, $this->gateway_transaction_id),
        ];
    }

    /**
     * A signed, expiring URL to the hosted card payment page.
     */
    private function checkoutUrl(): string
    {
        return URL::temporarySignedRoute(
            'payments.payway.checkout',
            now()->addMinutes(30),
            ['payment' => $this->id]
        );
    }

    private function shouldOfferCheckout(): bool
    {
        return $this->payment_method === 'card'
            && $this->payment_status === 'pending'
            && ! empty($this->gateway_response);
    }
}