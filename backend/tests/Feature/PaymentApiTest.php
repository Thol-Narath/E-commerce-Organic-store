<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Category;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymentApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        config([
            'payway.merchant_id' => 'ec000002',
            'payway.api_key' => 'test-api-key',
            'payway.base_url' => 'https://checkout-sandbox.payway.com.kh/',
        ]);
    }

    // ------------------------------------------------------------------
    // Payment methods
    // ------------------------------------------------------------------

    public function test_payment_methods_are_public_and_list_enabled_methods(): void
    {
        $this->getJson('/api/v1/payment-methods')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.methods.0.method', 'aba_pay')
            ->assertJsonPath('data.methods.0.payway_option', 'abapay_khqr_deeplink')
            ->assertJsonPath('data.methods.1.method', 'khqr')
            ->assertJsonPath('data.methods.2.method', 'card')
            ->assertJsonPath('data.methods.2.payway_option', 'cards');
    }

    public function test_disabled_methods_are_hidden(): void
    {
        config(['payway.methods.khqr' => false, 'payway.methods.card' => false]);

        $this->getJson('/api/v1/payment-methods')
            ->assertJsonCount(1, 'data.methods')
            ->assertJsonPath('data.methods.0.method', 'aba_pay');
    }

    // ------------------------------------------------------------------
    // Access & validation
    // ------------------------------------------------------------------

    public function test_unauthenticated_payment_creation_receives_401(): void
    {
        Http::preventStrayRequests();

        $this->postJson('/api/v1/orders/ORD-X/payments', ['payment_method' => 'aba_pay'])->assertStatus(401);
    }

    public function test_create_payment_requires_a_valid_method(): void
    {
        $token = $this->login($this->customer());
        $order = $this->pendingOrder($token);

        $this->withToken($token)->postJson("/api/v1/orders/{$order->order_number}/payments", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['payment_method'], 'data');

        $this->withToken($token)->postJson("/api/v1/orders/{$order->order_number}/payments", ['payment_method' => 'cod'])
            ->assertStatus(422);
    }

    public function test_create_payment_for_unknown_order_returns_404(): void
    {
        $token = $this->login($this->customer());

        $this->withToken($token)->postJson('/api/v1/orders/ORD-UNKNOWN/payments', ['payment_method' => 'aba_pay'])
            ->assertStatus(404);
    }

    public function test_customer_cannot_pay_another_customers_order(): void
    {
        Http::preventStrayRequests();
        $token = $this->login($this->customer());
        $order = $this->pendingOrder($token);

        $otherToken = $this->login($this->otherCustomer());
        $this->withToken($otherToken)->postJson("/api/v1/orders/{$order->order_number}/payments", ['payment_method' => 'aba_pay'])
            ->assertStatus(404);
    }

    public function test_already_paid_order_cannot_be_paid_again(): void
    {
        Http::preventStrayRequests();
        $token = $this->login($this->customer());
        $paid = $this->paidOrder($token);

        $this->withToken($token)->postJson("/api/v1/orders/{$paid->order_number}/payments", ['payment_method' => 'aba_pay'])
            ->assertStatus(409)
            ->assertJsonPath('message', 'This order has already been paid.');
    }

    public function test_cancelled_order_cannot_be_paid(): void
    {
        Http::preventStrayRequests();
        $token = $this->login($this->customer());
        $order = $this->pendingOrder($token);
        $order->update(['status' => 'cancelled']);

        $this->withToken($token)->postJson("/api/v1/orders/{$order->order_number}/payments", ['payment_method' => 'aba_pay'])
            ->assertStatus(409);
    }

    public function test_disabled_method_cannot_be_used(): void
    {
        Http::preventStrayRequests();
        config(['payway.methods.aba_pay' => false]);

        $token = $this->login($this->customer());
        $order = $this->pendingOrder($token);

        $this->withToken($token)->postJson("/api/v1/orders/{$order->order_number}/payments", ['payment_method' => 'aba_pay'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'The selected payment method is not available.');
    }

    public function test_gateway_unavailable_returns_502_and_payment_stays_pending(): void
    {
        Http::fake(['https://checkout-sandbox.payway.com.kh/*' => Http::response([], 500)]);

        $token = $this->login($this->customer());
        $order = $this->pendingOrder($token);

        $this->withToken($token)->postJson("/api/v1/orders/{$order->order_number}/payments", ['payment_method' => 'aba_pay'])
            ->assertStatus(502);

        $payment = Payment::where('order_id', $order->id)->firstOrFail();
        $this->assertSame('pending', $payment->payment_status);
    }

    // ------------------------------------------------------------------
    // Creating attempts
    // ------------------------------------------------------------------

    public function test_aba_pay_attempt_returns_qr_and_deeplink(): void
    {
        Http::fake([
            'https://checkout-sandbox.payway.com.kh/*' => Http::response([
                'status' => ['code' => '00', 'message' => 'Success!', 'tran_id' => 'PY123ABC'],
                'qr_string' => '00020101021230510016abaakhppxxx',
                'abapay_deeplink' => 'abamobilebank://ababank.com?type=payway&qrcode=0002',
                'checkout_qr_url' => 'https://checkout-uat.payway.com.kh/abc',
            ]),
        ]);

        $token = $this->login($this->customer());
        $order = $this->pendingOrder($token);

        $this->withToken($token)->postJson("/api/v1/orders/{$order->order_number}/payments", ['payment_method' => 'aba_pay'])
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.payment_method', 'aba_pay')
            ->assertJsonPath('data.payment_status', 'pending')
            ->assertJsonPath('data.amount', $order->total)
            ->assertJsonPath('data.currency', 'USD')
            ->assertJsonPath('data.order_number', $order->order_number)
            ->assertJsonPath('data.qr_string', '00020101021230510016abaakhppxxx')
            ->assertJsonPath('data.deeplink', 'abamobilebank://ababank.com?type=payway&qrcode=0002')
            ->assertJsonStructure(['data' => ['payment_number', 'expires_at']]);

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'payment_method' => 'aba_pay',
            'payment_status' => 'pending',
            'amount' => $order->total,
            'currency' => 'USD',
        ]);
    }

    public function test_khqr_attempt_returns_qr_string(): void
    {
        Http::fake([
            'https://checkout-sandbox.payway.com.kh/*' => Http::response([
                'status' => ['code' => '00', 'message' => 'Success!', 'tran_id' => 'KHQR99'],
                'qr_string' => '00020101021230510016abaakhppxxx',
                'abapay_deeplink' => null,
                'checkout_qr_url' => null,
            ]),
        ]);

        $token = $this->login($this->customer());
        $order = $this->pendingOrder($token);

        $this->withToken($token)->postJson("/api/v1/orders/{$order->order_number}/payments", ['payment_method' => 'khqr'])
            ->assertStatus(201)
            ->assertJsonPath('data.payment_method', 'khqr')
            ->assertJsonPath('data.qr_string', '00020101021230510016abaakhppxxx');
    }

    public function test_card_attempt_exposes_signed_checkout_url_but_not_html(): void
    {
        Http::fake([
            'https://checkout-sandbox.payway.com.kh/*' => Http::response(
                '<!DOCTYPE html><html><body>PayWay checkout</body></html>',
                200,
                ['Content-Type' => 'text/html']
            ),
        ]);

        $token = $this->login($this->customer());
        $order = $this->pendingOrder($token);

        $response = $this->withToken($token)->postJson("/api/v1/orders/{$order->order_number}/payments", ['payment_method' => 'card'])
            ->assertStatus(201)
            ->assertJsonPath('data.payment_method', 'card');

        $this->assertStringContainsString('/api/v1/payments/payway/checkout/', $response->json('data.checkout_url'));
        $this->assertArrayNotHasKey('qr_string', $response->json('data'));
        $this->assertArrayNotHasKey('html', $response->json('data'));

        $payment = Payment::where('order_id', $order->id)->firstOrFail();
        $this->assertNotEmpty($payment->gateway_response);
        $this->assertSame($payment->gateway_transaction_id, $payment->transaction_id);
    }

    public function test_purchase_request_sends_signed_hash_and_expected_fields(): void
    {
        Http::fake([
            'https://checkout-sandbox.payway.com.kh/*' => Http::response([
                'status' => ['code' => '00', 'message' => 'Success!', 'tran_id' => 'PY123'],
                'qr_string' => 'QR',
            ]),
        ]);

        $token = $this->login($this->customer());
        $order = $this->pendingOrder($token);

        $this->withToken($token)->postJson("/api/v1/orders/{$order->order_number}/payments", ['payment_method' => 'aba_pay'])
            ->assertStatus(201);

        Http::assertSent(function (Request $request) use ($order) {
            $body = (string) $request->body();

            $this->assertTrue($request->isMultipart(), 'Expected a multipart/form-data request.');
            $this->assertStringContainsString('/api/payment-gateway/v1/payments/purchase', $request->url());

            $fields = array_map(fn (string $name) => $this->multipartField($body, $name), [
                'merchant_id', 'tran_id', 'amount', 'items', 'firstname', 'lastname',
                'email', 'phone', 'type', 'payment_option', 'return_url', 'currency',
                'custom_fields', 'return_params', 'lifetime', 'skip_success_page',
                'view_type', 'hash', 'req_time', 'shipping',
            ]);
            $fields = array_combine([
                'merchant_id', 'tran_id', 'amount', 'items', 'firstname', 'lastname',
                'email', 'phone', 'type', 'payment_option', 'return_url', 'currency',
                'custom_fields', 'return_params', 'lifetime', 'skip_success_page',
                'view_type', 'hash', 'req_time', 'shipping',
            ], $fields);

            $this->assertIncludesFields($fields, [
                'merchant_id' => 'ec000002',
                'payment_option' => 'abapay_khqr_deeplink',
                'amount' => number_format((float) $order->total, 2, '.', ''),
                'currency' => 'USD',
                'type' => 'purchase',
                'view_type' => 'hosted_view',
                'skip_success_page' => '1',
            ]);
            $this->assertNotEmpty($fields['hash']);
            $this->assertNotEmpty($fields['tran_id']);
            $this->assertMatchesRegularExpression('/\d{14}/', $fields['req_time']);
            $this->assertSame($this->formatted($order->shipping_fee), $fields['shipping']);

            $items = json_decode(base64_decode($fields['items']), true);
            $this->assertIsArray($items);
            $this->assertNotEmpty($items);
            $this->assertArrayHasKey('name', $items[0]);
            $this->assertArrayHasKey('quantity', $items[0]);
            $this->assertArrayHasKey('price', $items[0]);

            $custom = json_decode(base64_decode($fields['custom_fields']), true);
            $this->assertSame($order->order_number, $custom['order_number'] ?? null);

            return true;
        });
    }

    private function formatted(float $value): string
    {
        return number_format($value, 2, '.', '');
    }

    /**
     * @param  array<string, mixed>  $fields
     * @param  array<string, mixed>  $expected
     */
    private function assertIncludesFields(array $fields, array $expected): void
    {
        foreach ($expected as $key => $value) {
            $this->assertArrayHasKey($key, $fields);
            $this->assertSame($value, $fields[$key]);
        }
    }

    private function multipartField(string $body, string $name): string
    {
        $pattern = '/name="'.preg_quote($name, '/').'"\r\n[^\r]+\r\n\r\n([^\r]*)/';

        if (preg_match($pattern, $body, $matches)) {
            return $matches[1];
        }

        return '';
    }

    // ------------------------------------------------------------------
    // Payment status
    // ------------------------------------------------------------------

    public function test_payment_status_returns_order_and_latest_payment(): void
    {
        Http::fake([
            'https://checkout-sandbox.payway.com.kh/*' => Http::response([
                'status' => ['code' => '00', 'message' => 'Success!', 'tran_id' => 'PY123'],
                'qr_string' => 'QR',
            ]),
        ]);

        $token = $this->login($this->customer());
        $order = $this->pendingOrder($token);
        $this->withToken($token)->postJson("/api/v1/orders/{$order->order_number}/payments", ['payment_method' => 'aba_pay']);

        $this->withToken($token)->getJson("/api/v1/orders/{$order->order_number}/payment-status")
            ->assertStatus(200)
            ->assertJsonPath('data.order_number', $order->order_number)
            ->assertJsonPath('data.order_status', 'pending')
            ->assertJsonPath('data.order_payment_status', 'unpaid')
            ->assertJsonPath('data.payment.payment_status', 'pending');
    }

    public function test_payment_status_scoped_to_owner(): void
    {
        $token = $this->login($this->customer());
        $order = $this->pendingOrder($token);

        $otherToken = $this->login($this->otherCustomer());
        $this->withToken($otherToken)->getJson("/api/v1/orders/{$order->order_number}/payment-status")
            ->assertStatus(404);
    }

    public function test_payment_status_returns_null_when_no_payment_exists(): void
    {
        $token = $this->login($this->customer());
        $order = $this->pendingOrder($token);

        $this->withToken($token)->getJson("/api/v1/orders/{$order->order_number}/payment-status")
            ->assertJsonPath('data.payment', null);
    }

    // ------------------------------------------------------------------
    // Refresh (check-transaction)
    // ------------------------------------------------------------------

    public function test_refresh_confirms_a_paid_transaction(): void
    {
        $token = $this->login($this->customer());
        $order = $this->pendingOrder($token);

        Http::fake([
            'https://checkout-sandbox.payway.com.kh/*' => Http::response([
                'data' => [
                    'payment_status_code' => 0,
                    'total_amount' => $order->total,
                    'payment_currency' => 'USD',
                    'apv' => '753786',
                    'payment_status' => 'APPROVED',
                ],
                'status' => ['code' => '00', 'message' => 'Success!', 'tran_id' => 'TXNID'],
            ]),
        ]);

        $this->withToken($token)->postJson("/api/v1/orders/{$order->order_number}/payments", ['payment_method' => 'aba_pay'])
            ->assertStatus(201);

        $payment = $order->payments()->first();

        $this->withToken($token)->postJson("/api/v1/orders/{$order->order_number}/payments/{$payment->id}/refresh")
            ->assertStatus(200)
            ->assertJsonPath('data.payment_status', 'paid');

        $order->refresh();
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('confirmed', $order->status);
    }

    public function test_refresh_rejects_amount_mismatch(): void
    {
        $token = $this->login($this->customer());
        $order = $this->pendingOrder($token);

        Http::fake([
            'https://checkout-sandbox.payway.com.kh/*' => Http::response([
                'data' => [
                    'payment_status_code' => 0,
                    'total_amount' => 99999.99,
                    'payment_currency' => 'USD',
                    'apv' => '753786',
                ],
                'status' => ['code' => '00', 'message' => 'Success!', 'tran_id' => 'TXNID'],
            ]),
        ]);

        $this->withToken($token)->postJson("/api/v1/orders/{$order->order_number}/payments", ['payment_method' => 'aba_pay'])
            ->assertStatus(201);

        $payment = $order->payments()->first();

        $this->withToken($token)->postJson("/api/v1/orders/{$order->order_number}/payments/{$payment->id}/refresh")
            ->assertStatus(422);

        $payment->refresh();
        $this->assertSame('pending', $payment->payment_status);
    }

    public function test_refresh_marks_declined_transaction_failed(): void
    {
        $token = $this->login($this->customer());
        $order = $this->pendingOrder($token);

        Http::fake([
            'https://checkout-sandbox.payway.com.kh/*' => Http::response([
                'data' => ['payment_status_code' => 3, 'payment_status' => 'DECLINED'],
                'status' => ['code' => '00', 'message' => 'Success!', 'tran_id' => 'TXNID'],
            ]),
        ]);

        $this->withToken($token)->postJson("/api/v1/orders/{$order->order_number}/payments", ['payment_method' => 'aba_pay'])
            ->assertStatus(201);

        $payment = $order->payments()->first();

        $this->withToken($token)->postJson("/api/v1/orders/{$order->order_number}/payments/{$payment->id}/refresh")
            ->assertStatus(200)
            ->assertJsonPath('data.payment_status', 'failed');

        $order->refresh();
        $this->assertSame('pending', $order->status);
        $this->assertSame('unpaid', $order->payment_status);
    }

    public function test_refresh_scoped_to_orders_payment(): void
    {
        Http::preventStrayRequests();
        $token = $this->login($this->customer());
        $order = $this->pendingOrder($token);

        $foreignPayment = Payment::where('order_id', '!=', $order->id)->firstOrFail();

        $this->withToken($token)->postJson("/api/v1/orders/{$order->order_number}/payments/{$foreignPayment->id}/refresh")
            ->assertStatus(404);
    }

    // ------------------------------------------------------------------
    // Hosted checkout page
    // ------------------------------------------------------------------

    public function test_hosted_checkout_page_requires_valid_signature(): void
    {
        Http::fake([
            'https://checkout-sandbox.payway.com.kh/*' => Http::response(
                '<!DOCTYPE html><html><body>PayWay checkout</body></html>',
                200,
                ['Content-Type' => 'text/html']
            ),
        ]);

        $token = $this->login($this->customer());
        $order = $this->pendingOrder($token);
        $this->withToken($token)->postJson("/api/v1/orders/{$order->order_number}/payments", ['payment_method' => 'card'])
            ->assertStatus(201);

        $payment = $order->payments()->first();
        $this->assertNotEmpty($payment->gateway_response);

        // Tampered signature must be rejected by the `signed` middleware.
        $this->getJson("/api/v1/payments/payway/checkout/{$payment->id}?signature=forged&expires=".now()->addMinutes(30)->timestamp)
            ->assertStatus(403);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    protected function pendingOrder(string $token): Order
    {
        $this->addToCart($token, $this->product(['price' => 25.00]), 1);

        return $this->checkout($token, ['status' => 'pending', 'payment_status' => 'unpaid']);
    }

    protected function paidOrder(string $token): Order
    {
        Http::preventStrayRequests();

        $this->addToCart($token, $this->product(['price' => 25.00]), 1);
        $order = $this->checkout($token, ['status' => 'pending']);

        Payment::create([
            'payment_number' => 'PAY-TEST-'.fake()->numerify('######'),
            'order_id' => $order->id,
            'payment_method' => 'cod',
            'amount' => $order->total,
            'currency' => 'USD',
            'payment_status' => 'paid',
            'paid_at' => now(),
            'expires_at' => now()->addMinutes(30),
        ]);

        $order->update(['payment_status' => 'paid', 'status' => 'confirmed']);

        return $order->fresh();
    }

    protected function addToCart(string $token, Product $product, int $quantity): void
    {
        $this->withToken($token)->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'quantity' => $quantity,
        ])->assertStatus(201);
    }

    /**
     * @param  array<string, mixed>  $paths  asserted JSON paths relative to
     *                                       the first order (e.g. `status`)
     */
    protected function checkout(string $token, array $paths): Order
    {
        $address = Address::where('user_id', $this->customer()->id)->where('is_default', true)->firstOrFail();

        $this->withToken($token)->postJson('/api/v1/checkout', ['address_id' => $address->id])
            ->assertStatus(201);

        foreach ($paths as $path => $expected) {
            $this->assertSame(
                $expected,
                $this->withToken($token)->getJson('/api/v1/orders')->json('data.orders.0.'.$path)
            );
        }

        return Order::where('user_id', $this->customer()->id)->orderByDesc('id')->firstOrFail();
    }

    protected function customer(): User
    {
        return User::where('email', 'maria@example.com')->firstOrFail();
    }

    protected function otherCustomer(): User
    {
        return User::where('email', 'john@example.com')->firstOrFail();
    }

    protected function login(User $user): string
    {
        $this->flushHeaders();
        $this->app->make(\Illuminate\Contracts\Auth\Factory::class)->forgetGuards();

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertStatus(200);

        return $response->json('data.token');
    }

    protected function product(array $attributes = []): Product
    {
        $attributes['category_id'] = $attributes['category_id'] ?? Category::factory()->create()->id;
        $attributes['stock_quantity'] = $attributes['stock_quantity'] ?? 10;

        return Product::factory()->active()->create($attributes);
    }
}