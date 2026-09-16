# ABA PayWay Setup Guide

> Phase 18 — ABA PayWay integration setup, naming, testing and go-live checklist.

This document explains how to configure the **ABA PayWay** payment gateway for the Organic Store
backend. It covers the environment variables, the hash rules, the callback URL, how to test
locally without real credentials, how to migrate to production, and the security constraints.

Related reading: `docs/payment-integration.md` (in-depth flow) and `config/payway.php`.

---

## 1. How the store talks to PayWay

The **Laravel backend is the only component that ever talks to PayWay**. React:

- sends **only** the chosen `payment_method`;
- never receives the merchant id, API key, or hosted card HTML;
- never decides whether a payment happened — it just polls `GET /orders/{order}/payment-status`.

The gateway client lives in `app/Services/AbaPaywayService.php` and is consumed by
`app/Services/PaymentService.php` (aba_pay / khqr / card methods). Bakong KHQR is a separate
gateway (`config/bakong.php`, `BakongService`) and is **not** affected by this setup.

---

## 2. Environment variables

All PayWay settings are in `backend/.env` under variables prefixed with **`ABAPAYWAY_`**. They are
read by `config/payway.php`. **Never** put these in the frontend (no `VITE_*` copies), and never
commit the real values — `.env` is git-ignored.

| Env key                     | Default (sandbox) | Meaning |
|-----------------------------|-------------------|---------|
| `ABAPAYWAY_ENV`              | `sandbox`         | Human label only (sandbox/production). **Not** a URL switch. |
| `ABAPAYWAY_MERCHANT_ID`      | —                 | Your PayWay merchant id. |
| `ABAPAYWAY_API_KEY`          | —                 | API key. Signs the purchase, check-transaction **and** webhook verification. |
| `ABAPAYWAY_PURCHASE_URL`    | `https://checkout-sandbox.payway.com.kh/api/payment-gateway/v1/payments/purchase` | Create-payment endpoint (sandbox). Use `https://checkout.payway.com.kh/...` for production. |
| `ABAPAYWAY_CHECK_URL`       | `https://checkout-sandbox.payway.com.kh/api/payment-gateway/v1/payments/check-transaction-2` | Reconcile/verify endpoint (sandbox). Use `https://checkout.payway.com.kh/...` for production. |
| `ABAPAYWAY_CALLBACK_URL`     | `/api/v1/payments/payway/webhook` | The path PayWay POSTs the payment result back to. In production this must be an **absolute, publicly reachable HTTPS URL**. |
| `ABAPAYWAY_RETURN_URL`       | `/payment`        | The SPA route used as `continue_success_url` when the customer returns. |
| `ABAPAYWAY_LIFETIME`         | `30`              | Attempt lifetime in minutes (minimum enforced: `3`). |
| `ABAPAYWAY_CURRENCY`         | `USD`             | Transaction currency (the order total always pays in this currency). |
| `ABAPAYWAY_TIMEOUT`          | `30`              | HTTP timeout in seconds for gateway calls. |
| `ABAPAYWAY_ABA_PAY_ENABLED`  | `true`            | Show/enable the `aba_pay` method in `GET /payment-methods`. |
| `ABAPAYWAY_KHQR_ENABLED`     | `true`            | Show/enable the `khqr` method. |
| `ABAPAYWAY_CARD_ENABLED`     | `true`            | Show/enable the `card` (hosted) method. |
| `ABAPAYWAY_VERIFY_TRANSACTION` | `true`          | Scheduler reconciliation of pending attempts (see §5). |

Example `.env` block (placeholders only — fill in real values):

```dotenv
ABAPAYWAY_ENV=sandbox
ABAPAYWAY_MERCHANT_ID=your_merchant_id
ABAPAYWAY_API_KEY=your_api_key
ABAPAYWAY_PURCHASE_URL=https://checkout-sandbox.payway.com.kh/api/payment-gateway/v1/payments/purchase
ABAPAYWAY_CHECK_URL=https://checkout-sandbox.payway.com.kh/api/payment-gateway/v1/payments/check-transaction-2
ABAPAYWAY_CALLBACK_URL=https://example.ngrok.app/api/v1/payments/payway/webhook
ABAPAYWAY_RETURN_URL=/payment
ABAPAYWAY_LIFETIME=30
ABAPAYWAY_CURRENCY=USD
ABAPAYWAY_TIMEOUT=30
ABAPAYWAY_ABA_PAY_ENABLED=true
ABAPAYWAY_KHQR_ENABLED=true
ABAPAYWAY_CARD_ENABLED=true
ABAPAYWAY_VERIFY_TRANSACTION=true
```

Apply changes with:

```bash
php artisan config:clear
php artisan config:cache   # only once the .env values are final, in production
```

---

## 3. Hash rules (do not change)

The exact rules used by `AbaPaywayService`:

- **Purchase hash** — `base64(HMAC-SHA512(api_key, message))` where `message` is the concatenation
  (no separators) of these field values **in this exact order**:
  `req_time, merchant_id, tran_id, amount, items, shipping, firstname, lastname, email, phone,
  type, payment_option, return_url, cancel_url, continue_success_url, return_deeplink, currency,
  custom_fields, return_params, payout, lifetime, additional_params, google_pay_token,
  skip_success_page`. `view_type` is **excluded**, and `hash` itself is never hashed.
- **Check-transaction hash** — `base64(HMAC-SHA512(api_key, req_time + merchant_id + tran_id))`.
- **Webhook signature** — sort the payload keys ascending (`ksort`), concatenate the values
  (nested arrays JSON-encoded), then HMAC-SHA512 with the API key and base64-encode; compare with
  `hash_equals` (timing-safe).

`req_time` is always UTC in `YmdHis` format. The gateway transaction id (`tran_id`) is generated
server-side (`PY{id}{random}`).

---

## 4. Callback (webhook) URL

1. PayWay posts the payment result to the `ABAPAYWAY_CALLBACK_URL` you set. The request carries an
   `X-PayWay-Hmac-SHA512` header, which `PayWayWebhookController` verifies **before** any state
   change. A bad/missing signature → `400`, an unknown `tran_id` → `404`. The webhook **never
   trusts the payload alone**: a successful callback still triggers an independent
   `check-transaction-2` call, and the payment is only marked `paid` when the gateway reports
   APPROVED **and** the amount/currency match the stored order total (mismatch → `422`, payment
   stays pending; gateway verification unavailable → `502`, PayWay re-sends the webhook). A
   callback with a non-`0` status → `failed`, order untouched.
2. For local development you need a **public HTTPS tunnel** so PayWay can reach the webhook, e.g.

   ```bash
   ngrok http 8000
   # set ABAPAYWAY_CALLBACK_URL=https://<your-subdomain>.ngrok.app/api/v1/payments/payway/webhook
   ```

   Every time the tunnel subdomain changes, update `ABAPAYWAY_CALLBACK_URL` and reload config. The
   `return_url`/`cancel_url`/`continue_success_url` are resolved against `APP_URL` by
   `AbaPaywayService::absoluteUrl()`, or can be set as absolute URLs directly.

3. In production, `ABAPAYWAY_CALLBACK_URL` must be the store's real public HTTPS webhook URL. The
   route exists at `POST /api/v1/payments/payway/webhook` and is public **by design** (PayWay
   cannot send a bearer token) — it is protected solely by the HMAC signature.

---

## 5. Reconciliation & expiry

`App\Jobs\CheckPendingPaymentsJob` runs on the scheduler (dev: `php artisan schedule:work`; prod:
cron `php artisan schedule:run`, plus a queue worker if `QUEUE_CONNECTION` is not `sync`). It:

1. **Expires** pending attempts whose `expires_at` has passed — order untouched.
2. **Reconciles** (when `ABAPAYWAY_VERIFY_TRANSACTION=true` **and** a merchant id + api key are
   set) up to a batch of pending attempts against `check-transaction-2`, so a payment the gateway
   settled but whose webhook was lost still flips to `paid`.

The migration command is a no-op business-wise; there is nothing to run.

---

## 6. Testing without real credentials

All backend payment tests use `Http::fake()` and never call PayWay:

```bash
php artisan test --filter=AbaPaywayServiceTest
php artisan test --filter=PaymentApiTest
php artisan test --filter=PayWayWebhookTest
php artisan test --filter=CheckPendingPaymentsJobTest
```

Covered behaviour (representative):

- Purchase is a **multipart** POST to the purchase URL, correct field order and payload hash
  (`PaymentApiTest`, `AbaPaywayServiceTest`).
- Check-transaction hash covers exactly `req_time + merchant_id + tran_id`.
- Status mapping `0 → paid`, `2 → pending`, `3 → failed`, `4 → refunded`, `7 → cancelled`,
  unknown → failed.
- Webhook HMAC: missing/forged → `400` with no state change; a `status=0` callback is settled only
  after an independent `check-transaction-2` reports APPROVED with a matching amount/currency
  (`paid`, idempotent on duplicates); gateway still pending → attempt stays pending; amount/currency
  mismatch → `422` with no state change; gateway unavailable → `502` (PayWay retries); declined →
  failed; unknown `tran_id` → `404`.
- Amount/currency mismatch confirmed by the gateway → `422`, payment stays pending, order stays
  `unpaid`/`pending`.
- Gateway unavailable (HTTP 500 on purchase or check) → `502`, attempt stays pending.
- `Http::fake()` **merges** registrations (first matching host-wide fake wins), so each test
  installs exactly one fake; tests that need distinct purchase vs check responses use distinct
  URL patterns (e.g. `.../payments/purchase*` vs `.../payments/check-transaction-2*`).

---

## 7. Sandbox / go-live checklist

- [ ] Register for PayWay and obtain sandbox `merchant_id` + `api_key`.
- [ ] Put them in `backend/.env` under `ABAPAYWAY_*` (never commit `.env`, never expose in React).
- [ ] Keep the URLs on the **sandbox** host until a full walkthrough passes.
- [ ] Run `php artisan config:clear` after any `.env` change.
- [ ] Expose the webhook via a public HTTPS tunnel and set `ABAPAYWAY_CALLBACK_URL` to it.
- [ ] Sandbox walkthrough for each method — ABA Pay (QR + deeplink), KHQR, card hosted page:
      success, decline, cancel, timeout; confirm `/payment-status` flips to `paid` and the order to
      `confirmed`.
- [ ] Confirm an amount/currency mismatch returns `422` and never marks paid.
- [ ] Verify duplicate/malformed webhooks are rejected **before** any DB write.
- [ ] Ensure the scheduler + queue are running (expiry + reconciliation).
- [ ] Re-run `php artisan test` and `npm run build` before each release.
- [ ] For production: flip the two URLs to `https://checkout.payway.com.kh/...`, set
      `ABAPAYWAY_ENV=production`, and put the real combo on an HTTPS host.

---

## 8. Security constraints

- **API key is server-side only.** No `VITE_ABAPAYWAY_*`, no frontend exposure, no logging, no
  hardcoding. Use `.env` + `config/payway.php`.
- **Totals are always computed by Laravel.** The client never supplies amount or currency; the
  gateway-reported amount/currency must match the stored order total before a payment is marked
  paid.
- **HMAC is the webhook's only protection** (signature verification + `hash_equals`).
- The `card` method stores the hosted checkout HTML in `payments.gateway_response` and only
  exposes a time-limited signed `checkout_url` to React.
- Do **not** run `migrate:fresh`/`db:wipe` against any existing data — payments and orders are
  append-only business records (`orders`/`payments` are never hard-deleted).
- Bakong integration (`BAKONG_*`) is independent and unaffected by this setup.