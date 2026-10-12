# Step 2: Technical plan

## Integration
* **PayPal Orders v2 REST API**, called server-side with OAuth 2.0 client credentials (Client ID + Secret).
* **PayPal JavaScript SDK** (`https://www.paypal.com/sdk/js`), loaded from PayPal: Buttons, plus optional `card-fields`.
* No third-party gateways and no PHP SDK dependency. The plugin uses the WordPress HTTP API with TLS verification.

## Payment flow
```
Client fills form ──► summary ──► clicks PayPal / Card button
      │
      ▼
POST /wp-json/phenix-payments/v1/orders     (WP REST nonce, rate limited)
  • validate name, email, reference, amount
  • amount comes from settings limits, or from a server-signed (HMAC) fixed-amount token
  • insert local record (status: created)
  • POST /v2/checkout/orders  (intent CAPTURE, custom_id, unique invoice_id, NO_SHIPPING,
                               PayPal-Request-Id for idempotency)
      │
      ▼
Buyer approves in PayPal popup / card form (card data stays inside PayPal)
      │
      ▼
POST /wp-json/phenix-payments/v1/orders/{id}/capture
  • atomic DB transition → "capturing" (only one request wins)
  • GET order from PayPal: check custom_id, amount, currency, status APPROVED, 3-D Secure result
  • POST capture (PayPal-Request-Id)
  • verify the capture amount/currency → completed | pending | declined
  • email the receipt once
      │
      ▼
PayPal webhook → POST /wp-json/phenix-payments/v1/webhook
  • verify via /v1/notifications/verify-webhook-signature (raw body, configured Webhook ID)
  • de-duplicate by event id
  • re-read the capture from the API, then update: completed / pending / denied / refunded / reversed
  • CHECKOUT.ORDER.APPROVED → server-side capture if the buyer closed the browser
```

## Status model
`created → capturing → completed | pending | declined | failed`
`created → cancelled` (buyer closed PayPal; this can never downgrade a paid payment)
`completed → partially_refunded → refunded`, or `reversed` (chargeback/dispute)

## Data stored (minimum)
Name, email, reference, description, amount (in cents), currency, PayPal order ID, capture ID, payment method label (for example "Card - VISA (credit)"), status, timestamps.
**Not stored:** card numbers, CVV, expiry, billing address, IP addresses, PayPal login.

## Security controls
| Control | Where |
|---|---|
| Nonce (CSRF) on browser endpoints | `Phenix_Pay_REST_Controller::check_nonce` |
| Server-side amount, signed fixed amounts | `Phenix_Pay_Payment_Service::sign_fixed / verify_fixed` |
| Server-side verification before marking paid | `capture()`, `order_matches_record()`, `apply_capture()` |
| Duplicate order prevention | attempt UUID + `PayPal-Request-Id` + unique `invoice_id` |
| Duplicate capture prevention | atomic `transition()` + `PayPal-Request-Id` |
| Webhook signature verification and replay protection | `handle_webhook()`, events table with a unique `event_id` |
| Capability checks (`manage_options`, filterable) | all admin screens and actions |
| Encrypted secrets (libsodium, key from wp-config salts) or wp-config constants | `Phenix_Pay_Settings` |
| Escaped output, prepared SQL, sanitized input | throughout |
| Redacting logger (no secrets, tokens or card-like numbers) | `Phenix_Pay_Logger` |
| Rate limit (10 orders / 10 min per client) | `check_rate_limit()` |
| CSV formula-injection protection on export | `csv_safe()` |
