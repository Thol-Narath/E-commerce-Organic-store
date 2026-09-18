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
     * Only the online gateway methods are accepted here, plus an optional
     * currency choice (USD or KHR). The payment amount is NEVER accepted from
     * the client — it always comes from the stored order total (converted
     * server-side using the store's configured KHR rate). Enabled/disabled
     * flags are enforced server-side by the PaymentService.
     */
    public function rules(): array
    {
        return [
            'payment_method' => ['required', 'string', Rule::in(['aba_pay', 'khqr', 'card', 'bakong'])],
            'currency' => ['sometimes', 'string', Rule::in(['USD', 'KHR'])],
        ];
    }

    /**
     * Normalize the client currency to uppercase (e.g. `usd`) so the service
     * can safely compare against the supported set.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('currency')) {
            $this->merge(['currency' => strtoupper((string) $this->input('currency'))]);
        }
    }
}