# Phenix Payments for PayPal

A custom WordPress plugin for **The Phenix LLC** (thephenixllc.com). Clients pay invoices and project fees by PayPal or by debit/credit card through **PayPal Checkout**, and the money is deposited into the connected PayPal **Business** account.

* Plugin source: [`phenix-payments/`](phenix-payments/)
* Installable ZIP: [`dist/phenix-payments.zip`](dist/phenix-payments.zip)
* Docs:
  1. [Feasibility for PayPal Canada (read first)](docs/1-FEASIBILITY.md)
  2. [Technical plan](docs/2-TECHNICAL-PLAN.md)
  3. [Install, configure and go live](docs/3-INSTALL-AND-DEPLOY.md)
  4. [Testing guide](docs/4-TESTING.md)

**Key requirement:** a PayPal **Personal** account cannot accept payments through this integration. Upgrade the existing Canadian account to **Business** (Settings → Account options → Upgrade). You do not need a second account.

Rebuild the ZIP: `cd /path/to/repo && rm -f dist/phenix-payments.zip && zip -rq dist/phenix-payments.zip phenix-payments`
