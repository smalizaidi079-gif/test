import type { AddressDetails } from "@/types/booking";
import type { AddressProvider, AddressSuggestion } from "./AddressProvider";
import { loadGoogleMaps } from "@/lib/googleMapsLoader";

// Real Google Places (Autocomplete + Geocoding) implementation of the same
// AddressProvider interface the mock system implements — see index.ts for
// the single line that switches between them. Requires
// VITE_GOOGLE_MAPS_API_KEY to be set (see .env.example).

let autocompleteService: google.maps.places.AutocompleteService | null = null;
let geocoder: google.maps.Geocoder | null = null;
let sessionToken: google.maps.places.AutocompleteSessionToken | null = null;

async function ensureServices() {
  const g = await loadGoogleMaps();
  autocompleteService ??= new g.maps.places.AutocompleteService();
  geocoder ??= new g.maps.Geocoder();
  sessionToken ??= new g.maps.places.AutocompleteSessionToken();
  return { autocompleteService, geocoder, sessionToken };
}

function findComponent(
  components: google.maps.GeocoderAddressComponent[] | undefined,
  type: string,
): string {
  return components?.find((c) => c.types.includes(type))?.long_name ?? "";
}

export class GooglePlacesProvider implements AddressProvider {
  async getSuggestions(query: string): Promise<AddressSuggestion[]> {
    if (query.trim().length < 2) return [];
    const { autocompleteService, sessionToken } = await ensureServices();

    return new Promise((resolve) => {
      autocompleteService.getPlacePredictions(
        { input: query, sessionToken, types: ["address"] },
        (predictions, status) => {
          if (status !== "OK" || !predictions) {
            resolve([]);
            return;
          }
          resolve(predictions.map((p) => ({ id: p.place_id, description: p.description })));
        },
      );
    });
  }

  async resolveSuggestion(suggestion: AddressSuggestion): Promise<AddressDetails> {
    const { geocoder } = await ensureServices();
    // A session ends once a selection is resolved — the next getSuggestions
    // call will start a fresh one, per Google's Autocomplete billing model.
    sessionToken = null;

    return new Promise((resolve, reject) => {
      geocoder.geocode({ placeId: suggestion.id }, (results, status) => {
        if (status !== "OK" || !results || !results[0]) {
          reject(new Error(`Could not resolve address (status: ${status}).`));
          return;
        }
        const result = results[0];
        const components = result.address_components;

        resolve({
          rawInput: suggestion.description,
          formatted: result.formatted_address,
          city:
            findComponent(components, "locality") ||
            findComponent(components, "postal_town") ||
            findComponent(components, "sublocality"),
          state: findComponent(components, "administrative_area_level_1"),
          zip: findComponent(components, "postal_code"),
          lat: result.geometry.location.lat(),
          lng: result.geometry.location.lng(),
          source: "google-places",
        });
      });
    });
  }
}
