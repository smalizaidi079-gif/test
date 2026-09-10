/// <reference types="vite/client" />

declare module "*.css?inline" {
  const content: string;
  export default content;
}

interface ImportMetaEnv {
  /** Optional: a Google Maps Platform API key. When set, the widget uses
   *  real Places Autocomplete/Geocoding + a satellite map instead of the
   *  built-in mock address system. See README "Google Maps integration". */
  readonly VITE_GOOGLE_MAPS_API_KEY?: string;
}

interface ImportMeta {
  readonly env: ImportMetaEnv;
}

// @types/google.maps declares the ambient `google` namespace but not this
// augmentation — needed so `window.google` type-checks in the loader/provider.
declare global {
  interface Window {
    google?: typeof google;
  }
}
