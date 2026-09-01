<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Category;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PayWayWebhookTest extends TestCase
{
    use RefreshDatabase;

    private string $apiKey = 'test-api-key';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        config([
            'payway.merchant_id' => 'ec000002',
            'payway.api_key' => $this->apiKey,
            'payway.base_url' => 'https://checkout-sandbox.payway.com.kh/',
        ]);
    }

    // ------------------------------------------------------------------
    // Signature handling
    // ------------------------------------------------------------------

    public function test_missing_signature_is_rejected(): void
    {
        Http::preventStrayRequests();

        $this->postJson('/api/v1/payments/payway/webhook', ['tran_id' => 'PY1', 'status' => '0'])
            ->assertStatus(400)
            ->assertJsonPath('message', 'Invalid callback signature.');
    }

    public function test_forged_signature_is_rejected_before_state_change(): void
    {
        Http::preventStrayRequests();
        $token = $this->login($this->customer());
        $order = $this->createPendingPayment($token);
        $payment = $order->payments()->first();

        $this->withHeaders(['X-PayWay-Hmac-SHA512' => 'forged-signature'])
            ->postJson('/api/v1/payments/payway/webhook', [
                'tran_id' => $payment->gateway_transaction_id,
                'status' => '0',
            ])
            ->assertStatus(400);

        $payment->refresh();
        $this->assertSame('pending', $payment->payment_status);

        $order->refresh();
        $this->assertSame('unpaid', $order->payment_status);
    }

    // ------------------------------------------------------------------
    // Success path
    // ------------------------------------------------------------------

    public function test_valid_signature_with_approved_status_confirms_payment(): void
    {
        Http::preventStrayRequests();
        $token = $this->login($this->customer());
        $order = $this->createPendingPayment($token);
        $payment = $order->payments()->first();
        $tranId = $payment->gateway_transaction_id;

        $payload = ['tran_id' => $tranId, 'status' => '0', 'apv' => '753786'];

        $this->withHeaders(['X-PayWay-Hmac-SHA512' => $this->signatureFor($payload)])
            ->postJson('/api/v1/payments/payway/webhook', $payload)
            ->assertStatus(200)
            ->assertJsonPath('message', 'Webhook processed successfully.');

        $payment->refresh();
        $this->assertSame('paid', $payment->payment_status);
        $this->assertSame('753786', $payment->gateway_reference);
        $this->assertNotNull($payment->paid_at);

        $order->refresh();
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('confirmed', $order->status);
    }

    public function test_duplicate_success_webhook_is_idempotent(): void
    {
        Http::preventStrayRequests();
        $token = $this->login($this->customer());
        $order = $this->createPendingPayment($token);
        $payment = $order->payments()->first();
        $tranId = $payment->gateway_transaction_id;

        $payload = ['tran_id' => $tranId, 'status' => '0', 'apv' => '753786'];
        $headers = ['X-PayWay-Hmac-SHA512' => $this->signatureFor($payload)];

        $this->withHeaders($headers)->postJson('/api/v1/payments/payway/webhook', $payload)->assertStatus(200);
        $this->withHeaders($headers)->postJson('/api/v1/payments/payway/webhook', $payload)->assertStatus(200);

        $this->assertSame(1, Payment::where('order_id', $order->id)
            ->where('payment_status', 'paid')
            ->count());
        $this->assertSame(1, Payment::where('order_id', $order->id)->count());
    }

    public function test_declined_status_marks_payment_failed_without_touching_order(): void
    {
        Http::preventStrayRequests();
        $token = $this->login($this->customer());
        $order = $this->createPendingPayment($token);
        $payment = $order->payments()->first();
        $tranId = $payment->gateway_transaction_id;

        $payload = ['tran_id' => $tranId, 'status' => '3'];

        $this->withHeaders(['X-PayWay-Hmac-SHA512' => $this->signatureFor($payload)])
            ->postJson('/api/v1/payments/payway/webhook', $payload)
            ->assertStatus(200);

        $payment->refresh();
        $this->assertSame('failed', $payment->payment_status);

        $order->refresh();
        $this->assertSame('unpaid', $order->payment_status);
        $this->assertSame('pending', $order->status);
    }

    public function test_unknown_transaction_is_rejected(): void
    {
        $payload = ['tran_id' => 'PY-NOT-FOUND', 'status' => '0'];

        $this->withHeaders(['X-PayWay-Hmac-SHA512' => $this->signatureFor($payload)])
            ->postJson('/api/v1/payments/payway/webhook', $payload)
            ->assertStatus(404)
            ->assertJsonPath('message', 'Transaction not found.');
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    protected function createPendingPayment(string $token): Order
    {
        $address = Address::where('user_id', $this->customer()->id)->where('is_default', true)->firstOrFail();
        $product = Product::factory()->active()->create([
            'category_id' => Category::factory()->create()->id,
            'price' => 25.00,
            'stock_quantity' => 10,
        ]);

        $this->withToken($token)->postJson('/api/v1/cart/items', ['product_id' => $product->id, 'quantity' => 1])
            ->assertStatus(201);
        $this->withToken($token)->postJson('/api/v1/checkout', ['address_id' => $address->id])
            ->assertStatus(201);

        $order = Order::where('user_id', $this->customer()->id)->orderByDesc('id')->firstOrFail();

        Payment::create([
            'payment_number' => 'PAY-WEB-'.uniqid(),
            'order_id' => $order->id,
            'payment_method' => 'aba_pay',
            'gateway' => 'payway',
            'amount' => $order->total,
            'currency' => 'USD',
            'payment_status' => 'pending',
            'expires_at' => now()->addMinutes(30),
            'gateway_transaction_id' => 'PY-WEB-'.uniqid(),
        ]);

        return $order->fresh();
    }

    protected function signatureFor(array $payload): string
    {
        ksort($payload);

        $raw = implode('', array_map(fn ($value) => is_array($value) ? json_encode($value) : (string) $value, $payload));

        return base64_encode(hash_hmac('sha512', $raw, $this->apiKey, true));
    }

    protected function customer(): User
    {
        return User::where('email', 'maria@example.com')->firstOrFail();
    }

    protected function login(User $user): string
    {
        $this->flushHeaders();

        return $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertStatus(200)->json('data.token');
    }
}