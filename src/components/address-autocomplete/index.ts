import { MockAddressProvider } from "./MockAddressProvider";
import type { AddressProvider } from "./AddressProvider";

// Single swap point: replace MockAddressProvider with GooglePlacesProvider
// (see GooglePlacesProvider.example.ts) when a real API key is ready.
// Nothing outside this file needs to know which provider is active.
export const addressProvider: AddressProvider = new MockAddressProvider();

export { AddressAutocomplete } from "./AddressAutocomplete";
export type { AddressProvider, AddressSuggestion } from "./AddressProvider";
