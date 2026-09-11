# Phase 8 — Payment Integration (ABA PayWay + Bakong KHQR)

> Phase 8 implementation notes. Version 1.2.0

This document describes how the Organic Store integrates ABA PayWay (developer.payway.com.kh)
for `aba_pay`, `khqr` and `card` payments, and the **Bakong Open API**
(api-bakong.nbc.gov.kh) for `bakong` KHQR payments. It explains the trust boundaries, the exact
gateway calls, signature rules, and how to verify the integration.

---

## 1. Trust Boundaries

The Laravel backend is the **only** party that talks to PayWay and the **only** authority on
payment state. React:

- sends **only** the chosen `payment_method`;
- never receives merchant credentials or the hosted card HTML;
- never sees card/CVV/PIN/OTP data;
- never decides whether a payment happened — it just polls the store and reacts to it.

The backend guarantees: the amount/currency come from `orders.total`; a payment becomes `paid`
only after the gateway confirms it (verified webhook or check-transaction `APPROVED`) **and**
the reported amount/currency match the stored total; exactly one successful attempt settles the
order (others are cancelled); failed/expired/cancelled attempts never touch the order.

---

## 2. Supported Methods & Configuration

`app/Enums/PaymentMethod` defines `aba_pay`, `khqr`, `card`, `cod`, `bank_transfer`, `online`
(reserved for later phases) and Bakong's `bakong`. The three PayWay methods (`isPayway()`) and
`bakong` (`isBakong()`) are the enabled payment methods; the others are reserved placeholders.

`config/payway.php` (values from `.env`):

| Env key                     | Default      | Meaning                                   |
|-----------------------------|--------------|-------------------------------------------|
| `PAYWAY_ENVIRONMENT`        | `sandbox`    | Human label; **not** the base URL switch  |
| `PAYWAY_MERCHANT_ID`        | —            | PayWay merchant id                        |
| `PAYWAY_API_KEY`            | —            | API key (signs purchase, check-transaction AND webhook verification) |
| `PAYWAY_BASE_URL`           | `https://checkout-sandbox.payway.com.kh/` | Gateway base URL (flip to `https://checkout.payway.com.kh/` for production) |
| `PAYWAY_TIMEOUT`            | `30`         | HTTP timeout (s)                          |
| `PAYWAY_CALLBACK_URL`       | `/api/v1/payments/payway/webhook` | Webhook URL PayWay posts back to (absolute in `.env`) |
| `PAYWAY_RETURN_URL`         | `/payment`   | SPA "continue_success_url"               |
| `PAYWAY_LIFETIME`           | `30`         | Attempt lifetime in minutes (min 3)       |
| `PAYWAY_CURRENCY`           | `USD`        | Transaction currency                      |
| `PAYWAY_ABA_PAY_ENABLED`    | `true`       | Toggles surfaced in `GET /payment-methods` |
| `PAYWAY_KHQR_ENABLED`       | `true`       |                                         |
| `PAYWAY_CARD_ENABLED`       | `true`       |                                         |
| `PAYWAY_VERIFY_TRANSACTION` | `true`       | Scheduler re-checks pending attempts      |

The `payment-methods` endpoint reads from config, so toggling a method never requires deploying
frontend code and keys never reach the browser.

---

## 3. Endpoints

| Method | Endpoint | Notes |
|--------|----------|-------|
| GET | `/api/v1/payment-methods` | public, no auth |
| POST | `/api/v1/orders/{orderNumber}/payments` | auth; 201 + `PaymentResource` |
| GET | `/api/v1/orders/{orderNumber}/payment-status` | auth; cheap DB read, the SPA polls this |
| POST | `/api/v1/orders/{orderNumber}/payments/{payment}/refresh` | auth; re-runs check-transaction-2 |
| POST | `/api/v1/payments/payway/webhook` | public by design; verified via `X-PayWay-Hmac-SHA512` |
| GET | `/api/v1/payments/payway/checkout/{payment}` | hosted card page behind a signed URL (30 min) |

Guards: unknown/foreign order → `404`; already-paid/cancelled order → `409`; invalid or
disabled method → `422`; gateway unavailable → `502` (attempt stays `pending`); bad webhook
signature → `400` before any state change; amount/currency mismatch on confirm → `422`.

---

## 4. Purchase Flow (attempt creation)

`PaymentService::createPayment()` → `PayWayService::createPayment()` issues

```
POST {base_url}/api/payment-gateway/v1/payments/purchase
```

as a multipart request. Fields are sent in the exact order used to compute the hash:

`req_time, merchant_id, tran_id, amount, items, shipping, firstname, lastname, email, phone,
type, payment_option, return_url, cancel_url, continue_success_url, return_deeplink, currency,
custom_fields, return_params, payout, lifetime, additional_params, google_pay_token,
skip_success_page` — then `hash`, then `view_type` (deliberately **not** hashed).

Notes:

- `payment_option` is `abapay_khqr_deeplink` (aba_pay & khqr) or `cards` (card); `type` is
  `purchase`; `skip_success_page` is `1`.
- `tran_id` is generated server-side (`PY{id}{random}`, <20 chars).
- `items`/`custom_fields` are base64-encoded JSON description/remark only — the gateway never
  derives totals from them; `amount` and `currency` are the authoritative values.
- `hash` = `base64(HMAC-SHA512(api_key, message))` where `message` is the concatenation of the
  values of the fields above **in the listed order**, with no separators and no `hash`/`view_type`
  included. `req_time` is UTC `YmdHis`.
- A `status.code = '00'` response returns `qr_string` and/or `abapay_deeplink` for the deeplink
  flow. For the `cards` flow PayWay answers with the **checkout HTML page** — it is stored as
  `payments.gateway_response` and never sent to React (the client only gets a signed
  `checkout_url`).

---

## 5. Callback Verification (webhook)

`POST /api/v1/payments/payway/webhook` is public because PayWay cannot carry a Bearer header.
It is therefore protected by the `X-PayWay-Hmac-SHA512` header:

1. `PayWayService::verifyCallbackSignature()` sorts the payload keys ascending (`ksort`),
   concatenates the values (nested arrays JSON-encoded), HMAC-SHA512-signs with the **API key**
   and base64-encodes it; comparison uses `hash_equals` (timing-safe). A mismatch or missing
   signature → `400` and **no state change**.
2. On `status=0` the payment is confirmed (`confirmPayment`, accepting the callback `apv`);
   `confirmPayment` is **idempotent** — duplicate successes are no-ops. Settling also cancels
   all other pending sibling attempts for the order in the same DB transaction.
3. Any other status marks the attempt `failed` without touching the order.
4. An unknown `tran_id` → `404`.

---

## 6. Reconcile (check-transaction-2)

`PaymentService::refresh()` (manual "Check payment status" and the scheduler) calls

```
POST {base_url}/api/payment-gateway/v1/payments/check-transaction-2
```

JSON body: `req_time`, `merchant_id`, `tran_id`, `hash` where the hash covers exactly
`req_time + merchant_id + tran_id`. Gateway `status.code` must be `00`; `data.payment_status_code`
maps to: `0 APPROVED → paid` · `2 PENDING → stays pending` · `3 DECLINED → failed` ·
`4 REFUNDED → refunded` · `7 CANCELLED → cancelled` (anything else → failed; a `code` of `6` on
the gateways' status is treated as failed here via `mapGatewayStatus`/job code check).

On `APPROVED`, the reported `total_amount`/`payment_amount` and `payment_currency` are verified
against the stored order total; a mismatch → `422` and the payment is **not** confirmed. Gateway
network errors leave the attempt pending for a later run.

---

## 7. Expiry & Scheduler

Each attempt gets `expires_at = now + PAYWAY_LIFETIME minutes`.

`App\Jobs\CheckPendingPaymentsJob` (dispatched every minute in `App\Console\Kernel` via
`Schedule::job(...)->everyMinute()`) does two things:

1. **Expire** pending attempts whose `expires_at` has passed (or, as a safety net, with null
   `expires_at` older than 24 h) — `markExpired()`; the order is untouched.
2. **Reconcile** (only when `verify_transaction` AND a `merchant_id`/`api_key` are configured) a
   small batch of still-pending attempts that have a `gateway_transaction_id`, so a payment the
   gateway already settled is marked `paid` even if the webhook never arrived.

Requires a running scheduler (`php artisan schedule:work` in dev; cron `php artisan schedule:run`
in production — with a queue worker when `QUEUE_CONNECTION` is not `sync`).

---

## 8. Frontend Flow

`frontend` (React + Bootstrap 5):

- `paymentService.js` — `methods()`, `create(orderNumber, method)`, `status(orderNumber)`,
  `refresh(orderNumber, paymentId)`.
- `usePaymentStatus(orderNumber)` — polls `status()` every ~4 s while a pending attempt exists
  and stops on a terminal state (paid → `/payment/success`, failed/cancelled → `/payment/failed`,
  expired → `/payment/expired`).
- `PaymentPage` (`/payment/:orderNumber`) — method selector → `create()` → method UI:
  - **ABA Pay**: QR (`qrcode.react`) + deeplink button.
  - **KHQR**: QR only.
  - **Card**: iframe to the signed `checkout_url`; card input happens on the bank's page.
  - Plus `PaymentTimer` countdown and a manual "Check payment status" (`/refresh`).
- Checkout redirects to the payment page after `POST /checkout`; "Pay Now"/"Complete Payment"
  buttons on the orders list/detail lead back to it; `OrderStatusBadge` gained `confirmed` and
  payment `expired`/`cancelled` states.

The browser can always be refreshed mid-payment: the payment page re-reads
`payment-status` and routes to the right outcome without losing state.

---

## 9. Test Strategy

Backend (`backend/tests/Feature`), all green with `Http::fake()` (the gateway is never called):

- `PaymentApiTest` — method list public/disabled; ownership & guard codes (401/404/409/422);
  gateway 500 → 502 + attempt stays pending; aba_pay/khqr/card responses (QR/deeplink/signed
  checkout_url, no raw HTML); purchase multipart payload + correct HMAC hash; payment-status
  resolution; refresh approve/decline/amount-mismatch/scope; tampered signed URL → 403.
- `PayWayWebhookTest` — missing/forged signature → 400 with no state change; `status=0` →
  paid+confirmed; duplicate webhook idempotent; declined → failed (order untouched); unknown
  tran → 404.
- `CheckPendingPaymentsJobTest` — expiry logic (incl. null `expires_at`), `verify_transaction`
  toggle, approve→paid, code 6→failed, gateway error/pending leave the attempt pending.

`Http::fake()` **merges** registrations (the first host-wide fake wins), so each test sets
exactly one fake and helpers never install fakes. Where a fixture must simulate an old
`created_at`, update the row after create (Eloquent always rewrites `created_at` on insert).

> **Live verification caveat:** no real PayWay sandbox credentials are available in this
> environment, so end-to-end calls against PayWay (purchase → webhook/redirect) could not be
> executed here. Go-live requires real sandbox `merchant_id` + `api_key` in `.env`, a publicly
> reachable `callback_url`, and a manual sandbox walkthrough of all three methods.

---

## 10. Go-Live Checklist

- [ ] Set real `PAYWAY_*` values in `.env`; keep `PAYWAY_BASE_URL` pointing at the sandbox until it passes.
- [ ] `PAYWAY_CALLBACK_URL` must be a publicly reachable HTTPS URL → the webhook path.
- [ ] Confirm `PAYWAY_RETURN_URL` (SPA) routing and the signing key base on the NGINX/Apache host.
- [ ] Sandbox walkthrough: ABA Pay (QR + deeplink), KHQR scan, and card hosted page (success,
      decline, cancellation, timeout).
- [ ] Verify amount/currency mismatch returns `422` and never marks paid.
- [ ] Verify duplicate/malformed webhooks are rejected before any DB write (log them).
- [ ] Scheduler running: `php artisan schedule:work` / cron entry (expiry + reconcile), plus a
      queue worker if `QUEUE_CONNECTION` is not `sync`.
- [ ] Re-run `php artisan test` and `npm run build` after each release.
- [ ] Bakong: set `BAKONG_ACCESS_TOKEN` / `BAKONG_ACCOUNT_ID`, point `BAKONG_BASE_URL` at the real
      API, and walk through a scan + deeplink payment (see section 11).

---

## 11. Bakong KHQR Integration

Bakong is Cambodia's national payment system (National Bank of Cambodia). The store generates a
standards-compliant **KHQR string locally** and uses the official Bakong Open API
(`https://api-bakong.nbc.gov.kh/v1`) only to (a) optionally fetch a deeplink for the generated QR
and (b) reconcile/confirm a payment later.

### 11.1 Trust Boundaries

- The **access token is server-side only** — it lives in `.env`/`config/bakong.php` and never
  reaches the browser.
- The QR string is generated **locally in PHP** (`App\Services\BakongQRGenerator`, EMVCo
  TLV + CRC-16), so payment initiation does not depend on Bakong API availability. If the
  optional `generate_deeplink_by_qr` call fails, the attempt is **still created** with the QR
  (201), just without a deeplink.
- Amount/currency always come from the stored `orders.total` / `config('bakong.currency')`; the
  client never supplies them.
- A payment becomes `paid` only when `check_transaction_by_md5` returns `responseCode 0` with
  `data` (paid) **and** the reported amount matches the order total.
- `gateway_transaction_id` stores the **MD5 of the QR string** — this is the id used to look the
  transaction up in Bakong. No DB schema change is required (it reuses the existing column).

### 11.2 Configuration (`config/bakong.php` from `.env`)

| Env key | Default | Meaning |
|---------|---------|---------|
| `BAKONG_ENABLED` | `false` | Surfaces `bakong` in `GET /payment-methods` and enables use |
| `BAKONG_BASE_URL` | `https://api-bakong.nbc.gov.kh/v1` | Bakong Open API base |
| `BAKONG_ACCESS_TOKEN` | — | Access token; register at `api-bakong.nbc.gov.kh/register/` (auto-renews via `/renew_token`) |
| `BAKONG_ACCOUNT_ID` | — | Your merchant/account id baked into the QR |
| `BAKONG_MERCHANT_NAME` | `Organic Store` | QR merchant name (≤ 25 chars) — **quote values with spaces in `.env`** |
| `BAKONG_MERCHANT_CITY` | `Phnom Penh` | QR merchant city (≤ 15 chars) — quote in `.env` |
| `BAKONG_CURRENCY` | `USD` | QR/transaction currency (`840` USD / `116` KHR) |
| `BAKONG_LIFETIME` | `15` | Attempt lifetime in minutes |
| `BAKONG_TIMEOUT` | `30` | HTTP timeout (s) |
| `BAKONG_VERIFY_TRANSACTION` | `true` | Scheduler re-checks pending Bakong attempts |

### 11.3 Flow

1. **Attempt creation** — `PaymentService::createPayment()` → `createBakongPayment()`:
   - `BakongQRGenerator::generate()` builds the EMVCo QR (tags: 00 format, 01 point-of-initiation
     `12`/`11`, 29 Bakong account info, 52 MCC, 53 currency, 54 amount, 58 country, 59 name,
     60 city, 63 CRC over everything). `generateMd5()` stores the QR's MD5 in
     `gateway_transaction_id`.
   - Optionally, `BakongService::generateDeeplink()` posts the QR to
     `POST {base_url}/generate_deeplink_by_qr`. A non-200 or an error is **not** fatal — the QR
     payment is returned (201) and the deeplink is simply absent.
2. **Scan / pay** — the customer scans the QR with any KHQR-enabled banking app (or taps the
   deeplink); the money moves at the **bank**, outside the store.
3. **Reconcile** — `PaymentService::refresh()` → `refreshBakong()` →
   `POST {base_url}/check_transaction_by_md5` with `{ md5: gateway_transaction_id }`:
   - `responseCode 0` + `data` present → `paid` (idempotent, `ConfirmPaymentRequest` amount check).
   - `responseCode 0` + no `data` → still `pending`.
   - any other code / HTTP error → stays `pending` for a later run (never failed on transient
     errors); network failure → pending.
   - A `401 Unauthorized` triggers a one-shot `renew_token` and retry.
   - Reported amount mismatch → `422`, never paid.
4. **Expiry & scheduler** — `CheckPendingPaymentsJob` handles both gateways: pending Bakong
   attempts expire via `expires_at = now + BAKONG_LIFETIME`, and while `BAKONG_VERIFY_TRANSACTION`
   is enabled plus the token/account are set it reconciles a batch of still-pending attempts that
   have a `gateway_transaction_id`.

### 11.4 Endpoints

Same set as PayWay (`/orders/{orderNumber}/payments`, `/refresh`, `/payment-methods`); there is
**no Bakong webhook** — reconciliation is pull-based. `POST /orders/.../payments` accepts
`payment_method: "bakong"` and returns the QR (`qr_string`) and optional `deeplink` in the
`PaymentResource` (already exposed for pending payments).

### 11.5 Frontend

- `PaymentMethodSelector` advertises `bakong` ("Bakong KHQR") from `payment-methods`.
- `PaymentPage` renders `BakongPayment` for the `bakong` method: the QR image
  (`qrcode.react`) plus a "Pay with Bakong" deeplink button when a deeplink is present.
- The existing `usePaymentStatus` polling + refresh button work unchanged.

### 11.6 Tests

- `tests/Unit/BakongQRGeneratorTest` — EMVCo structure (payload format, point-of-initiation,
  tag 29 GUID/account sub-tags, currency/amount tags), static vs dynamic QR, deterministic MD5,
  CRC-16 recomputation, name/city truncation.
- `PaymentApiTest` Bakong cases (via `enableBakong()` helper) — method listed/hidden; attempt
  returns QR + MD5 without any gateway call for generation; refresh: confirm paid, keeps pending
  while unpaid, rejects amount mismatch; deeplink failure still 201.
- `CheckPendingPaymentsJobTest` covers the generic reconcile path shared with PayWay.

> **Live verification caveat:** no real Bakong credentials are available in this environment, so
> live calls are unverified. Once you register, put a **sandbox/test account** in `.env`
> (`BAKONG_ACCESS_TOKEN`, `BAKONG_ACCOUNT_ID`), enable `BAKONG_ENABLED=true`, then scan the QR
> with the Bakong app and watch `/payment-status` flip to `paid`. Remember the access token has a
> 90-day validity and renews automatically via `/renew_token`.