// Loads the Google Maps JavaScript API script exactly once, on demand.
// Both GooglePlacesProvider (autocomplete + geocoding) and SatelliteMap
// (the map view) share this loader so the script is never injected twice.

let loadPromise: Promise<typeof google> | null = null;

const LOAD_TIMEOUT_MS = 10_000;

export function isGoogleMapsConfigured(): boolean {
  return Boolean(import.meta.env.VITE_GOOGLE_MAPS_API_KEY);
}

function loadScript(apiKey: string): Promise<typeof google> {
  return new Promise((resolve, reject) => {
    const callbackName = "__iqwGoogleMapsCallback";
    (window as unknown as Record<string, () => void>)[callbackName] = () => {
      delete (window as unknown as Record<string, unknown>)[callbackName];
      if (window.google) {
        resolve(window.google);
      } else {
        reject(new Error("Google Maps script loaded but window.google is missing."));
      }
    };

    const script = document.createElement("script");
    script.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(
      apiKey,
    )}&libraries=places&callback=${callbackName}&v=weekly`;
    script.async = true;
    script.onerror = () => reject(new Error("Failed to load the Google Maps script."));
    document.head.appendChild(script);
  });
}

export function loadGoogleMaps(): Promise<typeof google> {
  if (typeof window !== "undefined" && window.google?.maps) {
    return Promise.resolve(window.google);
  }
  if (loadPromise) return loadPromise;

  const apiKey = import.meta.env.VITE_GOOGLE_MAPS_API_KEY;
  if (!apiKey) {
    return Promise.reject(new Error("VITE_GOOGLE_MAPS_API_KEY is not set."));
  }

  // A blocked/very slow network (ad-blocker, corporate firewall, offline)
  // can leave the <script> tag's load hanging with neither onload nor
  // onerror ever firing — race it against a timeout so callers (spinners,
  // the satellite map) always resolve to a failure instead of hanging.
  loadPromise = Promise.race([
    loadScript(apiKey),
    new Promise<typeof google>((_, reject) =>
      setTimeout(() => reject(new Error("Timed out loading Google Maps.")), LOAD_TIMEOUT_MS),
    ),
  ]).catch((err) => {
    loadPromise = null; // allow a retry on the next call
    throw err;
  });

  return loadPromise;
}
