<?php

namespace Database\Factories;

use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_id' => 1,
            'payment_number' => 'PAY-'.now()->format('Ymd').'-'.fake()->unique()->numerify('######'),
            'payment_method' => fake()->randomElement(['aba_pay', 'khqr', 'card', 'cod', 'bank_transfer', 'online']),
            'gateway' => 'payway',
            'transaction_id' => null,
            'gateway_transaction_id' => fake()->unique()->numerify('PY##########'),
            'gateway_reference' => null,
            'amount' => 0.00,
            'currency' => 'USD',
            'payment_status' => fake()->randomElement(['pending', 'paid']),
            'qr_string' => null,
            'deeplink' => null,
            'gateway_response' => null,
            'expires_at' => now()->addMinutes(30),
            'paid_at' => null,
        ];
    }
}