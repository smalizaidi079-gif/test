// NOT WIRED IN. This file is a reference stub showing how to plug in the
// real Google Places API later. To activate it:
//
//   1. Rename this file to `GooglePlacesProvider.ts`.
//   2. Add your API key (via a WordPress-side proxy/endpoint — never ship
//      a Places key straight into client JS if you can avoid it; a tiny
//      PHP proxy endpoint in the plugin is the safe pattern).
//   3. In `index.ts`, change the import from MockAddressProvider to this
//      class. That is the ONLY other file that needs to change — every
//      UI component talks to the shared AddressProvider interface.
//
// import type { AddressDetails } from "@/types/booking";
// import type { AddressProvider, AddressSuggestion } from "./AddressProvider";
//
// export class GooglePlacesProvider implements AddressProvider {
//   constructor(private readonly proxyEndpoint: string) {}
//
//   async getSuggestions(query: string): Promise<AddressSuggestion[]> {
//     const res = await fetch(
//       `${this.proxyEndpoint}?action=autocomplete&input=${encodeURIComponent(query)}`,
//     );
//     const data = await res.json();
//     return (data.predictions ?? []).map((p: any) => ({
//       id: p.place_id,
//       description: p.description,
//     }));
//   }
//
//   async resolveSuggestion(suggestion: AddressSuggestion): Promise<AddressDetails> {
//     const res = await fetch(
//       `${this.proxyEndpoint}?action=details&place_id=${encodeURIComponent(suggestion.id)}`,
//     );
//     const data = await res.json();
//     const components = data.result.address_components;
//     const find = (type: string) =>
//       components.find((c: any) => c.types.includes(type))?.long_name ?? "";
//
//     return {
//       rawInput: suggestion.description,
//       formatted: data.result.formatted_address,
//       city: find("locality"),
//       state: find("administrative_area_level_1"),
//       zip: find("postal_code"),
//       lat: data.result.geometry.location.lat,
//       lng: data.result.geometry.location.lng,
//       source: "google-places",
//     };
//   }
// }
export {};
