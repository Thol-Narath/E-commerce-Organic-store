<?php

namespace App\Http\Requests\Payment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreatePaymentRequest extends FormRequest
{
    /**
     * Any authenticated customer may initiate a payment.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Only the online gateway methods are accepted here. The payment amount is
     * NEVER accepted from the client — it always comes from the stored order
     * total, always in USD (the store's only accepted currency). Enabled/
     * disabled flags are enforced server-side by the PaymentService.
     */
    public function rules(): array
    {
        return [
            'payment_method' => ['required', 'string', Rule::in(['aba_pay', 'khqr', 'card', 'bakong'])],
        ];
    }
}