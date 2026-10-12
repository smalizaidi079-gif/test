# Installation, configuration and going live

## 0. Before you start
* **Back up your site** (files and database).
* Your site must use **HTTPS** (thephenixllc.com does).
* PHP 7.4+ and WordPress 6.0+.

## 1. Install the plugin
1. WordPress admin → **Plugins → Add New → Upload Plugin** → choose `phenix-payments.zip` → **Install Now** → **Activate**.
2. A new **Phenix Payments** menu appears in the left sidebar, with **Payments** and **Settings** under it.

Updating from an older version: **Plugins → Phenix Payments → Deactivate → Delete**, then upload the new ZIP. Payment records are kept unless you ticked "Delete all data on uninstall".

## 2. Sandbox setup (test mode)
1. Log in at **developer.paypal.com** with your PayPal login. You type it on PayPal's site, never into WordPress.
2. **Apps & Credentials → Sandbox** → open "Default Application", or click **Create App** (type: Merchant).
3. Copy the **Client ID** and **Secret**.
4. In the same app → **Webhooks → Add Webhook**:
   * URL: `https://thephenixllc.com/wp-json/phenix-payments/v1/webhook`
   * Events: `CHECKOUT.ORDER.APPROVED`, `CHECKOUT.ORDER.COMPLETED`, `PAYMENT.CAPTURE.COMPLETED`, `PAYMENT.CAPTURE.PENDING`, `PAYMENT.CAPTURE.DENIED`, `PAYMENT.CAPTURE.DECLINED`, `PAYMENT.CAPTURE.REFUNDED`, `PAYMENT.CAPTURE.REVERSED`
   * Save, then copy the **Webhook ID**.
5. WordPress → **Phenix Payments → Settings**:
   * Mode: **Sandbox**
   * Paste the Sandbox Client ID, Secret and Webhook ID → **Save Changes**
   * Click **Test connection**. You should see **Connected**.
6. Create a page (for example "Pay") and add `[phenix_payments]`. In Elementor, use the **Shortcode** widget.
7. **Exclude the payment page from caching** (LiteSpeed, WP Rocket, Cloudflare and similar). Cached pages carry expired security tokens, and clients would see "Your session has expired".

Shortcode examples:
```
[phenix_payments]
[phenix_payments amount="500" reference="INV-1024" description="Brand strategy – milestone 1" title="Pay invoice INV-1024"]
```
You can also pre-fill an open-amount form by link: `https://thephenixllc.com/pay/?ref=INV-1024&amount=500`. The amount is still validated by the server.

## 3. Optional: keep secrets in wp-config.php (most secure)
Add these lines above `/* That's all, stop editing! */`:
```php
define( 'PHENIX_PAY_SANDBOX_CLIENT_ID', '...' );
define( 'PHENIX_PAY_SANDBOX_CLIENT_SECRET', '...' );
define( 'PHENIX_PAY_SANDBOX_WEBHOOK_ID', '...' );
define( 'PHENIX_PAY_LIVE_CLIENT_ID', '...' );
define( 'PHENIX_PAY_LIVE_CLIENT_SECRET', '...' );
define( 'PHENIX_PAY_LIVE_WEBHOOK_ID', '...' );
```
Constants override the settings screen. Secrets entered on the settings screen are stored encrypted.

## 4. Going live
1. **Upgrade your PayPal account to Business**: paypal.com → Settings → Account options → *Upgrade to a Business account*. Confirm your email and link and confirm your bank account.
2. developer.paypal.com → **Apps & Credentials → Live** → **Create App** → copy the Live Client ID and Secret.
3. In the Live app, add the same webhook URL and events, then copy the **Live Webhook ID**.
4. Optional: in PayPal, apply for **Advanced Credit and Debit Card Payments** if you want card fields embedded in the form. Then tick "Enable embedded card fields" in settings.
5. Settings: paste the **Live** credentials → set Mode to **Live** → confirm → Save → **Test connection** → **Connected**.
6. Make one small real payment (for example $1.00) with your own card, check it in **Phenix Payments → Payments** and in PayPal, then refund it from PayPal. The status should change to *Refunded* through the webhook.
7. Keep the sandbox records for reference (filter "Live only" to hide them), or delete them.

## 5. Day-to-day
* **Payments** screen: search by name, email, reference or PayPal ID; filter by status, mode and date; export CSV; open **Details**; **Refresh from PayPal**.
* **Refunds**: issue them in your PayPal account. The plugin updates automatically.
* **Log** tab: shows errors such as rejected credentials or failed webhook verification.
