<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Jobs\CheckPendingPaymentsJob;
use App\Models\Address;
use App\Models\Category;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CheckPendingPaymentsJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        config([
            'payway.verify_transaction' => false,
            'payway.merchant_id' => 'ec000002',
            'payway.api_key' => 'test-api-key',
            'payway.base_url' => 'https://checkout-sandbox.payway.com.kh/',
        ]);
    }

    // ------------------------------------------------------------------
    // Expiry
    // ------------------------------------------------------------------

    public function test_expired_pending_payment_becomes_expired(): void
    {
        $payment = $this->pendingPayment(['expires_at' => now()->subMinutes(10)]);

        (new CheckPendingPaymentsJob)->handle(app(\App\Services\PaymentService::class));

        $payment->refresh();
        $this->assertSame(PaymentStatus::Expired->value, $payment->payment_status);

        $payment->order->refresh();
        $this->assertSame('unpaid', $payment->order->payment_status);
        $this->assertSame('pending', $payment->order->status);
    }

    public function test_null_expires_at_expired_when_older_than_24_hours(): void
    {
        $payment = $this->pendingPayment([
            'expires_at' => null,
            'created_at' => now()->subHours(25),
        ]);

        (new CheckPendingPaymentsJob)->handle(app(\App\Services\PaymentService::class));

        $payment->refresh();
        $this->assertSame(PaymentStatus::Expired->value, $payment->payment_status);
    }

    public function test_pending_payment_with_future_expires_at_stays_pending(): void
    {
        Http::preventStrayRequests();
        $payment = $this->pendingPayment(['expires_at' => now()->addMinutes(5)]);

        (new CheckPendingPaymentsJob)->handle(app(\App\Services\PaymentService::class));

        $payment->refresh();
        $this->assertSame(PaymentStatus::Pending->value, $payment->payment_status);
    }

    // ------------------------------------------------------------------
    // Reconciliation
    // ------------------------------------------------------------------

    public function test_reconciliation_does_not_run_when_verify_transaction_disabled(): void
    {
        config(['payway.verify_transaction' => false]);
        Http::preventStrayRequests();

        $payment = $this->pendingPayment(['gateway_transaction_id' => 'PY-GW-123']);

        (new CheckPendingPaymentsJob)->handle(app(\App\Services\PaymentService::class));

        $payment->refresh();
        $this->assertSame(PaymentStatus::Pending->value, $payment->payment_status);
        Http::assertNothingSent();
    }

    public function test_reconciliation_confirms_an_approved_transaction(): void
    {
        $payment = $this->pendingPayment([
            'gateway_transaction_id' => 'PY-GW-OK',
            'expires_at' => now()->addMinutes(30),
        ]);

        config(['payway.verify_transaction' => true]);

        Http::fake([
            'https://checkout-sandbox.payway.com.kh/*' => Http::response([
                'data' => [
                    'payment_status_code' => 0,
                    'total_amount' => $payment->amount,
                    'payment_currency' => 'USD',
                    'apv' => 'APV-RECON-1',
                ],
                'status' => ['code' => '00', 'message' => 'Success!', 'tran_id' => 'PY-GW-OK'],
            ]),
        ]);

        (new CheckPendingPaymentsJob)->handle(app(\App\Services\PaymentService::class));

        $payment->refresh();
        $this->assertSame(PaymentStatus::Paid->value, $payment->payment_status);
        $this->assertSame('APV-RECON-1', $payment->gateway_reference);

        $payment->order->refresh();
        $this->assertSame('paid', $payment->order->payment_status);
        $this->assertSame('confirmed', $payment->order->status);
    }

    public function test_gateway_not_found_code_6_marks_payment_failed(): void
    {
        $payment = $this->pendingPayment([
            'gateway_transaction_id' => 'PY-GW-6',
            'expires_at' => now()->addMinutes(30),
        ]);

        config(['payway.verify_transaction' => true]);

        Http::fake([
            'https://checkout-sandbox.payway.com.kh/*' => Http::response([
                'status' => ['code' => '6', 'message' => 'tran_id not found'],
            ]),
        ]);

        (new CheckPendingPaymentsJob)->handle(app(\App\Services\PaymentService::class));

        $payment->refresh();
        $this->assertSame(PaymentStatus::Failed->value, $payment->payment_status);
    }

    public function test_gateway_error_does_not_change_payment_status(): void
    {
        $payment = $this->pendingPayment([
            'gateway_transaction_id' => 'PY-GW-ERR',
            'expires_at' => now()->addMinutes(30),
        ]);

        config(['payway.verify_transaction' => true]);

        Http::fake([
            'https://checkout-sandbox.payway.com.kh/*' => Http::response([], 500),
        ]);

        (new CheckPendingPaymentsJob)->handle(app(\App\Services\PaymentService::class));

        $payment->refresh();
        $this->assertSame(PaymentStatus::Pending->value, $payment->payment_status);
    }

    public function test_pending_transaction_from_gateway_keeps_payment_pending(): void
    {
        $payment = $this->pendingPayment([
            'gateway_transaction_id' => 'PY-GW-PEND',
            'expires_at' => now()->addMinutes(30),
        ]);

        config(['payway.verify_transaction' => true]);

        Http::fake([
            'https://checkout-sandbox.payway.com.kh/*' => Http::response([
                'data' => [
                    'payment_status_code' => 2,
                    'total_amount' => $payment->amount,
                    'payment_currency' => 'USD',
                ],
                'status' => ['code' => '00', 'message' => 'Success!', 'tran_id' => 'PY-GW-PEND'],
            ]),
        ]);

        (new CheckPendingPaymentsJob)->handle(app(\App\Services\PaymentService::class));

        $payment->refresh();
        $this->assertSame(PaymentStatus::Pending->value, $payment->payment_status);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    protected function pendingPayment(array $overrides = []): Payment
    {
        $token = $this->login($this->customer());
        $product = Product::factory()->active()->create([
            'category_id' => Category::factory()->create()->id,
            'price' => 25.00,
            'stock_quantity' => 10,
        ]);
        $address = Address::where('user_id', $this->customer()->id)->where('is_default', true)->firstOrFail();

        $this->addToCart($token, $product);
        $order = $this->checkout($token, $address);

        $payment = Payment::create(array_merge([
            'payment_number' => 'PAY-TEST-'.uniqid(),
            'order_id' => $order->id,
            'payment_method' => 'aba_pay',
            'gateway' => 'payway',
            'amount' => $order->total,
            'currency' => 'USD',
            'payment_status' => PaymentStatus::Pending->value,
            'expires_at' => now()->addMinutes(30),
            'gateway_transaction_id' => null,
        ], $overrides));

        // Eloquent always rewrites created_at on insert, so force the fixture
        // value through when a test needs an older created_at.
        if (array_key_exists('created_at', $overrides)) {
            $this->app['db']->table('payments')->where('id', $payment->id)
                ->update(['created_at' => $overrides['created_at']]);
            $payment = $payment->fresh();
        }

        return $payment;
    }

    protected function addToCart(string $token, Product $product): void
    {
        $this->withToken($token)->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertStatus(201);
    }

    protected function checkout(string $token, Address $address): Order
    {
        $this->withToken($token)->postJson('/api/v1/checkout', ['address_id' => $address->id])->assertStatus(201);

        return Order::where('user_id', $this->customer()->id)->orderByDesc('id')->firstOrFail();
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