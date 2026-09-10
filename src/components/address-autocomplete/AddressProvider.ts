import type { AddressDetails } from "@/types/booking";

/**
 * Contract every address-suggestion source must implement.
 *
 * To go live with Google Places later:
 *   1. Create GooglePlacesProvider.ts implementing this same interface
 *      (see GooglePlacesProvider.example.ts for a ready-to-fill stub).
 *   2. Flip the single export in `index.ts` to use it instead of
 *      MockAddressProvider.
 * No changes are needed anywhere else — <AddressAutocomplete /> and the
 * rest of the booking flow only ever talk to this interface.
 */
export interface AddressSuggestion {
  id: string;
  /** Text shown in the suggestion list. */
  description: string;
}

export interface AddressProvider {
  /** Return suggestions for partial user input (debounced by the caller). */
  getSuggestions(query: string): Promise<AddressSuggestion[]>;
  /** Resolve a chosen suggestion into full address details. */
  resolveSuggestion(suggestion: AddressSuggestion): Promise<AddressDetails>;
}
