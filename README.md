# Instant Quote & Booking Widget (Prototype)

A LawnStarter-inspired instant quote and booking wizard, built as a
standalone, embeddable React widget for a WordPress site. It runs as a
**zero-cost demo out of the box** (mock address suggestions, no API key
needed), and can be switched to **real Google Places + a satellite map**
by setting one environment variable — no code changes required either
way. There is still no payment processor; that was out of scope.

## What's inside

- A 7-step instant quote wizard: Service → Address (+ satellite view) →
  Frequency → Property Details → Instant Quote → Customer Info →
  Confirmation.
- A demo pricing engine with a live, animated price breakdown.
- An address-autocomplete system with two interchangeable backends behind
  one interface:
  - **Mock** (default, no setup): type `123 Ma` → get `123 Main Street,
    New York`, `123 Maple Avenue, New York`, etc. — pure local logic, no
    network calls.
  - **Google Places** (opt-in): real autocomplete + geocoding, plus an
    interactive satellite map of the confirmed property. Activated by
    setting `VITE_GOOGLE_MAPS_API_KEY` — see "Google Maps integration"
    below.
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
    address-autocomplete/   # Address system — mock by default, Google Places opt-in
      AddressProvider.ts           interface every provider implements
      MockAddressProvider.ts       zero-cost demo implementation (default)
      GooglePlacesProvider.ts      real Places autocomplete + geocoding
      AddressAutocomplete.tsx      UI component (provider-agnostic)
      SatelliteMap.tsx             interactive satellite map (Google Places mode only)
      index.ts                     <- the ONE file that picks the active provider
    pricing-engine/          # Pure business logic, no React
      pricingConfig.ts             all $ amounts / discount %s live here
      pricingEngine.ts              calculateQuote(), formatCurrency()
    booking-flow/            # The wizard UI
      QuoteWidget.tsx               orchestrator / shell
      ProgressBar.tsx, StepShell.tsx, NavigationButtons.tsx
      steps/Step1..Step7*.tsx       (order: Service, Address, Frequency, PropertyDetails, Quote, CustomerInfo, Confirmation)
    ui/                      # Shared UI atoms (Button, SelectableCard, icons, ...)
  data/
    servicesConfig.ts        # Step 1 service list
    propertyQuestionsConfig.ts  # Which questions/add-ons show per service (Step 4)
  hooks/useQuoteWizard.ts    # All wizard state + validation, separate from UI
  lib/googleMapsLoader.ts    # Loads the Google Maps script once, on demand, with a timeout
  types/booking.ts           # Shared domain types
  App.tsx, main.tsx          # Dev/standalone entry (plain DOM mount)
  embed.tsx                  # WordPress entry (Shadow DOM mount + window.InstantQuoteWidget.init)
wordpress-plugin/instant-quote-widget/   # Ready-to-zip WP plugin
scripts/copy-to-wp-plugin.mjs           # Copies the built embed JS into the plugin
.env.example                # Copy to .env to set VITE_GOOGLE_MAPS_API_KEY
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

## Google Maps integration

Real Google Places autocomplete/geocoding and a live satellite map are
already built and wired in behind the same `AddressProvider` interface
the mock system uses — turning it on is a **config change, not a code
change**.

### Enabling it

1. In Google Cloud Console: create/select a project, enable **billing**,
   and enable these three APIs: **Maps JavaScript API**, **Places API**,
   **Geocoding API**.
2. Create an API key, then **restrict it** (Credentials → your key →
   Application restrictions → HTTP referrers) to the domain(s) you'll
   deploy to. This is a browser key, not a secret — it ships inside the
   built JS bundle, so the referrer restriction is what actually protects
   it from being used elsewhere.
3. Copy `.env.example` to `.env` and paste in the key:
   ```
   VITE_GOOGLE_MAPS_API_KEY=your-key-here
   ```
4. Rebuild (`npm run dev` or `npm run build:all`).

That's it — `src/components/address-autocomplete/index.ts` checks for
that env var and automatically switches from `MockAddressProvider` to
`GooglePlacesProvider` at build time. Leave `.env` empty/absent and the
zero-cost mock stays active — nothing else changes or breaks.

### What changes once it's on

- **Address step**: real Places autocomplete suggestions and geocoding
  (instead of the fake street-name list), plus an interactive satellite
  map (Google's "hybrid" view — imagery + labels) centered on the
  resolved property with a pin, and a small details row (city, state,
  ZIP, coordinates).
- If the Maps script fails to load or times out (bad key, network
  issue), the widget degrades gracefully — suggestions just come back
  empty and the map shows an inline error — it never hangs or crashes.

### Notes for whoever wires up the real key later

- `.env` / `.env.*` are gitignored (only `.env.example` is committed) —
  never commit a real key.
- For the **WordPress plugin build**, the key gets baked into
  `instant-quote-widget.js` at build time (`npm run build:wp`), same
  referrer-restriction rule applies — restrict it to the WP site's
  domain.
- Want to add a different provider later (e.g. Mapbox)? Implement the
  same `AddressProvider` interface (`src/components/address-autocomplete/AddressProvider.ts`)
  and point `index.ts` at it — nothing else in the wizard needs to change.

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
| Address autocomplete + satellite map | Real (Google Places), opt-in via `VITE_GOOGLE_MAPS_API_KEY`; falls back to the zero-cost mock when unset |
| Customer submission storage | Saved to `localStorage` only — swap `wizard.submit()` in `src/hooks/useQuoteWizard.ts` for a real API call (e.g. a WP REST endpoint) when going live |
| Payments | Not implemented (not requested for this prototype) |

## Testing notes

The full 7-step flow (in both mock and Google-configured modes), the
mock address suggestions, the WordPress Shadow-DOM embed (rendered
inside a deliberately theme-hostile host page), the iframe auto-resize
path, and the Google Maps failure path (invalid/blocked key — verifying
the loader's timeout resolves the UI instead of hanging) were all
exercised in a headless browser during development. No console errors
were observed in any of these paths.
