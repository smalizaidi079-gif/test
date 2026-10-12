# Testing guide (Sandbox)

Keep Mode = **Sandbox**. Create a sandbox **Personal (buyer)** account under developer.paypal.com → **Testing Tools → Sandbox Accounts**. For cards, use the **Card Generator** (Testing Tools → Credit Card Generator) to make test Visa or Mastercard numbers. Never use a real card in sandbox.

| # | Test | Steps | Expected |
|---|---|---|---|
| 1 | **Successful PayPal payment** | Fill the form → Review → PayPal button → log in as the sandbox buyer → Pay | Success screen with a PayPal transaction ID. Payments list shows *Completed*, Method *PayPal*. Admin and client emails arrive. |
| 2 | **Guest card payment** | Click **Debit or Credit Card** → enter a generated test card | *Completed*, Method "Card". If the button is missing, PayPal is not offering guest checkout for that buyer country or currency. |
| 3 | **Cancelled payment** | Open PayPal → close the popup or click "Cancel and return" | Blue "Payment cancelled. You have not been charged." Record *Cancelled*. You can retry. |
| 4 | **Declined card** | In the guest card form, use a test card with a decline trigger. In sandbox, PayPal's negative-testing mode can force `INSTRUMENT_DECLINED` (developer.paypal.com → Sandbox account → Negative testing). | Error "Your payment was declined…", the buyer can choose another method, record *Declined*. No capture in PayPal. |
| 5 | **Pending** | Pay in a currency your sandbox business account does not hold, with "ask me" currency acceptance enabled for that sandbox account | *Pending (PayPal review)*. Accept the payment in the sandbox business account → the webhook changes it to *Completed*. |
| 6 | **Duplicate requests** | Double-click Pay, or refresh during "Processing" and try again | Only one PayPal order and one capture. A second capture call returns the existing result. |
| 7 | **Invalid amounts** | Enter `0`, `-5`, `abc`, `1.234`, or above the maximum | Inline error, no PayPal window. |
| 8 | **Tampered fixed amount** | On a fixed-amount form, change the hidden `amount` input with browser DevTools, then pay | You are charged the shortcode amount. A tampered `amount_token` gives "This payment form is out of date". |
| 9 | **Webhook verification** | developer.paypal.com → your app → Webhooks: confirm deliveries return **200**. Then refund a sandbox payment from the sandbox business account (Activity → Refund). | Record → *Refunded* or *Partially refunded*, with the refunded amount shown. |
| 10 | **Forged webhook** | `curl -X POST https://YOURSITE/wp-json/phenix-payments/v1/webhook -H 'Content-Type: application/json' -d '{"id":"x","event_type":"PAYMENT.CAPTURE.REFUNDED"}'` | HTTP **400**. Log: "Webhook signature verification FAILED". No record changes. Note: PayPal's *Webhook simulator* sends mock events that cannot be verified, so expect 400 from it too. |
| 11 | **Fake success** | Call `/orders/ANYID/capture` without a real approved order | 404/409. Nothing is marked paid. |
| 12 | **Expired session** | Leave the page open for over 24h, or from a cached page | "Your session has expired. Please refresh." |
| 13 | **Access control** | Log in as an Editor or Subscriber | No Phenix Payments menu. Admin URLs return "not allowed". |
| 14 | **Secrets** | Settings page source / Log tab | The secret never appears. The field shows "saved — leave blank to keep". |
| 15 | **Mobile** | Phone or DevTools at 375px width | Single-column layout, no sideways scrolling, large tap targets. |

## Automated tests included in this repository
`tests/` contains the harness used during development. It runs **without real PayPal**, because PayPal's servers are not reachable from the build environment:
* `tests/mock-paypal.php`: a mu-plugin that simulates PayPal REST responses (OAuth, orders, capture, captures, webhook verification).
* `tests/integration.php`: 53 server-side checks (validation, nonce, idempotency, amount tampering, declined/pending, 3-D Secure, webhooks, replay, refunds, rate limit, secret redaction).
* `tests/e2e.js`: Playwright browser test of the form (desktop and mobile) with a stubbed PayPal SDK.

Results at build time: **53/53 integration checks and 17/17 browser checks passed against the mock**. These tests prove the plugin's own logic. They do **not** replace a real Sandbox run with real PayPal (tests 1–5 and 9 above).
