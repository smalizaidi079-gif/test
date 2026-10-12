=== Phenix Payments for PayPal ===
Contributors: thephenixllc
Tags: paypal, payments, invoices, credit card, checkout
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.1
License: GPLv2 or later

Secure invoice and project payments for The Phenix LLC using PayPal Checkout: PayPal, debit and credit cards (guest checkout), and optional embedded card fields.

== Description ==

* Shortcode `[phenix_payments]` with fixed or client-entered amounts.
* PayPal Orders v2 API, with the amount set and verified on the server.
* Card data is entered only in PayPal-hosted components and never reaches WordPress.
* Webhooks are verified through PayPal's verify-webhook-signature API.
* Prevents duplicate orders and duplicate captures.
* Admin payment records with search, filters and CSV export.

Requires a PayPal **Business** account. See the plugin's Setup guide tab.

== Installation ==

1. Plugins → Add New → Upload Plugin → choose phenix-payments.zip → Install → Activate.
2. Phenix Payments → Settings: enter Sandbox Client ID, Secret and Webhook ID, then click "Test connection".
3. Add `[phenix_payments]` to a page. In Elementor, use the Shortcode widget.

== Changelog ==

= 1.0.1 =
* Fix: Settings page was not accessible because the submenu was registered before its parent.
* Fix: the form script config is now attached on block themes and page builders that render shortcodes early.

= 1.0.0 =
* Initial release.
