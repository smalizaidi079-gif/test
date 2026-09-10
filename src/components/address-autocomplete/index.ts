import { MockAddressProvider } from "./MockAddressProvider";
import { GooglePlacesProvider } from "./GooglePlacesProvider";
import { isGoogleMapsConfigured } from "@/lib/googleMapsLoader";
import type { AddressProvider } from "./AddressProvider";

// Single swap point: which provider is active is decided once, here, based
// on whether a Google Maps API key is configured (see .env.example).
// Nothing outside this file needs to know which one is active — set
// VITE_GOOGLE_MAPS_API_KEY and rebuild to go from mock to live Places data,
// or unset it to fall back to the zero-cost demo.
export const addressProvider: AddressProvider = isGoogleMapsConfigured()
  ? new GooglePlacesProvider()
  : new MockAddressProvider();

export { AddressAutocomplete } from "./AddressAutocomplete";
export { SatelliteMap } from "./SatelliteMap";
export type { AddressProvider, AddressSuggestion } from "./AddressProvider";
