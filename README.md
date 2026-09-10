# Instant Quote & Booking Widget (Prototype)

A LawnStarter-inspired instant quote and booking wizard, built as a
standalone, embeddable React widget for a WordPress site. This is a
**client-demo prototype** — no paid APIs, no Google Maps/Places, no
payment processor. Everything (address suggestions, pricing) is powered
by mock logic that's structured so the real integrations can be dropped
in later with minimal changes.

## What's inside

- A 7-step instant quote wizard: Service → Address → Property Details →
  Frequency → Instant Quote → Customer Info → Confirmation.
- A demo pricing engine with a live, animated price breakdown.
- A mock address-autocomplete system (type `123 Ma` → get `123 Main
  Street, New York`, `123 Maple Avenue, New York`, etc.) built behind an
  interface so it's a one-file swap to Google Places later.
- Two build outputs from one codebase:
  - **`dist/`** — a standalone page (for hosting + `<iframe>` embedding).
  - **`dist-embed/`** — a single JS file that self-mounts into a Shadow
    DOM (for direct WordPress embedding via shortcode/Elementor).
- A ready-to-install WordPress plugin (`wordpress-plugin/instant-quote-widget/`)
  that wires up the shortcode `[instant_quote_widget]`.

## Folder structure

```
src/
  components/
    address-autocomplete/   # Mock address system — swappable for Google Places
      AddressProvider.ts           interface every provider implements
      MockAddressProvider.ts       the demo implementation (active)
      GooglePlacesProvider.example.ts   reference stub for later
      AddressAutocomplete.tsx      UI component (provider-agnostic)
      index.ts                     <- the ONE file to edit to swap providers
    pricing-engine/          # Pure business logic, no React
      pricingConfig.ts             all $ amounts / discount %s live here
      pricingEngine.ts              calculateQuote(), formatCurrency()
    booking-flow/            # The wizard UI
      QuoteWidget.tsx               orchestrator / shell
      ProgressBar.tsx, StepShell.tsx, NavigationButtons.tsx
      steps/Step1..Step7*.tsx
    ui/                      # Shared UI atoms (Button, SelectableCard, icons, ...)
  data/
    servicesConfig.ts        # Step 1 service list
    propertyQuestionsConfig.ts  # Which questions/add-ons show per service (Step 3)
  hooks/useQuoteWizard.ts    # All wizard state + validation, separate from UI
  types/booking.ts           # Shared domain types
  App.tsx, main.tsx          # Dev/standalone entry (plain DOM mount)
  embed.tsx                  # WordPress entry (Shadow DOM mount + window.InstantQuoteWidget.init)
wordpress-plugin/instant-quote-widget/   # Ready-to-zip WP plugin
scripts/copy-to-wp-plugin.mjs           # Copies the built embed JS into the plugin
```

Business logic (pricing, address resolution, wizard state) is fully
separated from presentation — steps only call hook functions / pure
functions, never compute prices or filter addresses themselves.

## Running locally

Requires Node 18+.

```bash
npm install
npm run dev
```

Opens the wizard at `http://localhost:5173` in a plain dev harness (no
WordPress needed to iterate on the UI).

Other scripts:

```bash
npm run typecheck   # TypeScript, no emit
npm run build       # Standalone SPA -> dist/   (for iframe hosting)
npm run build:embed # WordPress bundle -> dist-embed/instant-quote-widget.js
npm run build:wp    # build:embed + copies the JS into wordpress-plugin/.../assets/
npm run build:all   # build + build:wp
```

## Embedding into WordPress

Two supported paths — pick whichever is easier for your setup. The
**WordPress plugin (Option A)** is the recommended path since it needs no
external hosting and keeps everything inside WP.

### Option A — WordPress plugin (recommended)

1. Run `npm run build:wp` (or use the already-built copy committed in
   `wordpress-plugin/instant-quote-widget/assets/instant-quote-widget.js`).
2. Zip the `wordpress-plugin/instant-quote-widget/` folder.
3. In WP Admin → Plugins → Add New → Upload Plugin, upload the zip, then
   **Activate**.
4. Add the shortcode to any page/post:
   ```
   [instant_quote_widget]
   ```
   Optional width control: `[instant_quote_widget max_width="720px"]`.
5. **Elementor HTML widget**: instead of the shortcode, drop an "HTML"
   widget and paste:
   ```html
   <div data-instant-quote-widget></div>
   ```
   The plugin auto-detects Elementor-built pages and loads the script for
   you. (If you use a page builder other than Elementor, add the
   shortcode `[instant_quote_widget]` in a Shortcode/HTML widget instead —
   that always works.)

Because the widget renders inside a **Shadow DOM**, it carries its own
Tailwind styles and is immune to theme CSS collisions in both directions
— verified by rendering it inside a deliberately hostile test theme
(clashing fonts, colors, button/input resets) with zero visual bleed.

### Option B — `<iframe>` embed

Host the contents of `dist/` anywhere static (Netlify, Vercel, GitHub
Pages, an S3 bucket, or a WP subfolder served directly) and embed:

```html
<iframe
  src="https://your-hosted-widget-url.com/"
  style="width:100%; border:0; display:block;"
  height="900"
  id="quoteFrame"
></iframe>
```

The widget auto-reports its content height to the parent window via
`postMessage` (see `src/lib/iframeAutoResize.ts`), so you can wire up
auto-resize instead of guessing a fixed height:

```html
<script>
  window.addEventListener("message", (event) => {
    if (event.data && event.data.type === "instant-quote-widget:resize") {
      document.getElementById("quoteFrame").style.height = event.data.height + "px";
    }
  });
</script>
```

This was tested end-to-end: the iframe starts at a default height and
snaps to the widget's real content height once it loads.

## Replacing the mock address system with Google Places later

The whole point of `src/components/address-autocomplete/` is that this
is a **one-file swap**:

1. Rename `GooglePlacesProvider.example.ts` → `GooglePlacesProvider.ts`
   and fill in the two methods (`getSuggestions`, `resolveSuggestion`) —
   the stub already shows the exact shape expected, matching the
   `AddressProvider` interface used everywhere else.
2. Because a Places API key should never ship directly into client JS,
   route requests through a tiny WordPress REST endpoint or PHP AJAX
   handler that holds the key server-side and proxies
   Autocomplete/Place Details calls. The stub's `proxyEndpoint` param is
   built for exactly that.
3. In `src/components/address-autocomplete/index.ts`, change:
   ```ts
   export const addressProvider: AddressProvider = new MockAddressProvider();
   ```
   to:
   ```ts
   export const addressProvider: AddressProvider = new GooglePlacesProvider("/wp-json/iqw/v1/places");
   ```
4. Rebuild (`npm run build:all`). **No other file changes anywhere** —
   `AddressAutocomplete.tsx` and the rest of the wizard only ever talk to
   the `AddressProvider` interface, not the mock implementation directly.

## Pricing engine — tuning the demo numbers

All dollar amounts and discount percentages live in one file:
`src/components/pricing-engine/pricingConfig.ts` (base price, size
adjustments, condition adjustments, per-add-on price, frequency
discounts). `pricingEngine.ts` is pure calculation logic with no UI
dependency — safe to unit test or move server-side later.

## Deployment instructions

**For the WordPress plugin path:** no separate deployment — installing
the plugin zip on the target WP site is the deployment.

**For the iframe path:** deploy the static `dist/` folder to any static
host:

- **Netlify / Vercel**: connect the repo, set build command
  `npm run build`, publish directory `dist`.
- **Manual/static hosting**: run `npm run build` locally and upload the
  contents of `dist/` to any static file host or a subfolder on the
  existing WP server.

## What's mocked vs. production-ready

| Piece | Status |
|---|---|
| Wizard UI, animations, pricing math, validation | Production-quality — reusable as-is |
| Address autocomplete | Mocked, swappable via one interface (see above) |
| Customer submission storage | Saved to `localStorage` only — swap `wizard.submit()` in `src/hooks/useQuoteWizard.ts` for a real API call (e.g. a WP REST endpoint) when going live |
| Payments | Not implemented (not requested for this prototype) |

## Testing notes

The full 7-step flow, the mock address suggestions, the WordPress
Shadow-DOM embed (rendered inside a deliberately theme-hostile host page),
and the iframe auto-resize path were all exercised in a headless browser
during development. No console errors were observed in any of these
paths.
