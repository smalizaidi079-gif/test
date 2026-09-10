import type { AddressDetails } from "@/types/booking";
import type { AddressProvider, AddressSuggestion } from "./AddressProvider";

// Fake "predictive" address source: given "123 Ma" it returns street names
// starting with "Ma" at house number 123, e.g. "123 Main Street, New York" —
// mirroring how a real autocomplete API responds, without calling one.
const STREET_NAMES = [
  "Main Street",
  "Maple Avenue",
  "Market Street",
  "Madison Avenue",
  "Magnolia Court",
  "Oak Lane",
  "Oakwood Drive",
  "Cedar Court",
  "Sunset Boulevard",
  "Willow Way",
  "River Road",
  "Highland Drive",
  "Park Place",
  "Elm Street",
  "Pine Street",
  "Lakeview Drive",
];

const DEFAULT_CITY = { city: "New York", state: "NY", zip: "10001" };

const SIMULATED_LATENCY_MS = 220;
const MAX_RESULTS = 5;

function parseQuery(query: string): { houseNumber: string; streetPrefix: string } {
  const match = query.trim().match(/^(\d+)\s*(.*)$/);
  if (match) {
    return { houseNumber: match[1], streetPrefix: match[2].toLowerCase() };
  }
  return { houseNumber: "", streetPrefix: query.trim().toLowerCase() };
}

export class MockAddressProvider implements AddressProvider {
  async getSuggestions(query: string): Promise<AddressSuggestion[]> {
    await wait(SIMULATED_LATENCY_MS);

    const trimmed = query.trim();
    if (trimmed.length < 2) return [];

    const { houseNumber, streetPrefix } = parseQuery(trimmed);
    const houseNumberToUse = houseNumber || "123";

    const matches = STREET_NAMES.filter(
      (name) => streetPrefix.length === 0 || name.toLowerCase().startsWith(streetPrefix),
    ).slice(0, MAX_RESULTS);

    return matches.map((street, idx) => ({
      id: `${houseNumberToUse}-${street}-${idx}`,
      description: `${houseNumberToUse} ${street}, ${DEFAULT_CITY.city}`,
    }));
  }

  async resolveSuggestion(suggestion: AddressSuggestion): Promise<AddressDetails> {
    await wait(120);

    const [street] = suggestion.description.split(", ");

    return {
      rawInput: suggestion.description,
      formatted: `${street}, ${DEFAULT_CITY.city}, ${DEFAULT_CITY.state} ${DEFAULT_CITY.zip}`,
      city: DEFAULT_CITY.city,
      state: DEFAULT_CITY.state,
      zip: DEFAULT_CITY.zip,
      source: "mock",
    };
  }
}

function wait(ms: number) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}
