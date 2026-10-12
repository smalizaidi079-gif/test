# Step 1: PayPal Canada feasibility check

_Researched October 2026. PayPal changes its products often, so confirm anything marked "verify" in your own PayPal account._

## Bottom line

| Question | Answer |
|---|---|
| Can a **Personal** PayPal account accept card payments on your website through PayPal's API? | **No.** PayPal Checkout and the REST API are merchant products, so you need a **Business** account. PayPal's developer docs say a personal account must be upgraded to a business account to go live. |
| Do you need a second account? | **No.** In Canada you can upgrade your existing account: **paypal.com → Settings (gear) → Account options → "Upgrade to a Business account"**. Your email, balance and history are kept. |
| Is the upgrade reversible? | Generally no. PayPal says most business accounts cannot be downgraded. Business accounts also cannot receive "friends & family" personal payments, and PayPal may pull a credit report when you convert. |
| Can clients pay by card **without a PayPal account**? | **Yes**, with a Business account, in two ways (see below). |
| Where does the money go? | Into your PayPal Business balance. From there you withdraw it to your Canadian bank account. |
| Currency | USD and CAD both work for a Canadian business account. The plugin defaults to **USD**. Receiving USD may create a USD balance, or PayPal may convert it according to your account preferences. |
| Fees (verify) | PayPal Canada's published domestic commercial rate is **2.90% + $0.30 CAD**. International buyers add **+0.80% (US)** or **+1.00% (other countries)**, plus currency conversion if any. Advanced card processing may have its own rate. Check paypal.com/ca/business/fees. |

## Card-payment options for a Canadian Business account

### A. Standard PayPal Checkout: "Debit or Credit Card" button (guest checkout)
* This is part of the standard JavaScript SDK Buttons integration.
* The buyer clicks the black **Debit or Credit Card** button and enters card details in a PayPal-hosted form. No PayPal account is needed.
* **No extra approval is required** beyond a Business account.
* **Limitation:** PayPal decides when this button appears, based on the buyer's country, the currency and risk checks. It is not guaranteed for every buyer. When it is hidden, buyers can still pay with PayPal.
* The plugin enables this by default.

### B. Advanced Credit and Debit Card Payments (embedded card fields)
* Card fields appear inside your own form. PayPal hosts them as iframes, so card data still never reaches your server.
* **Canada is supported**: Visa, Mastercard, American Express (CAD and USD only) and JCB (CAD only).
* **Requires PayPal approval** of your Business account for advanced card processing. PayPal reviews the business, which can take days. The Canadian "Online Card Payment Services Agreement" applies, and PayPal can impose conditions such as also offering PayPal Credit where available.
* Includes 3-D Secure (the plugin requests `SCA_WHEN_REQUIRED`).
* The plugin includes this as an **optional** setting. The fields appear only if PayPal reports your account as eligible (`CardFields.isEligible()`). Otherwise option A is used automatically.

## What you need to provide (never your PayPal password)
1. A PayPal **Business** account (an upgraded version of your current account).
2. From developer.paypal.com → **Apps & Credentials**:
   * a **Sandbox** Client ID + Secret, for testing
   * a **Live** Client ID + Secret, which needs the Business account
3. A **Webhook ID** for each environment (see the Deployment guide).

> **Note:** If you already have a Client ID and Secret while your account is still Personal, they are almost certainly **Sandbox** credentials. Sandbox credentials only work with `api-m.sandbox.paypal.com`. The plugin's "Test connection" button tells you whether the credentials work for the selected mode.

## Decision
Build on **PayPal Checkout (Orders v2 REST API + official JavaScript SDK)**:
* Smart Buttons (PayPal + guest "Debit or Credit Card" + optional Pay Later) work as soon as the account is Business.
* Embedded card fields are optional and appear automatically once PayPal approves advanced card processing.

## Sources
* [PayPal Canada: How do I upgrade or downgrade my PayPal account?](https://www.paypal.com/ca/cshelp/article/comment-puis-je-modifier-mon-type-de-compte-paypal-help339)
* [PayPal Canada User Agreement (business account conversion)](https://www.paypal.com/ca/legalhub/useragreement-full)
* [PayPal developer: advanced card payments eligibility (Canada card brands)](https://developer.paypal.com/docs/checkout/advanced/eligibility/)
* [PayPal developer: configure sandbox and live accounts (business account required to go live)](https://developer.paypal.com/docs/multiparty/accept-payments/)
* [PayPal Canada Online Card Payment Services Agreement](https://www.paypal.com/ca/legalhub/paypal/pocpsa-full)
* [PayPal Canada merchant fees](https://www.paypal.com/ca/business/fees)
* [PayPal developer: verify webhook signature](https://developer.paypal.com/api/webhooks/v1/verify-webhook-signature-post)
