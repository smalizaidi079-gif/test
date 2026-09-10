// Shared domain types for the booking flow. UI components and business
// logic (pricing engine, address provider) both depend on these — this
// file has no dependency on React or any specific vendor/API.

export type ServiceId =
  | "lawn-mowing"
  | "landscaping"
  | "garden-maintenance"
  | "cleaning"
  | "other";

export type PropertySize = "small" | "medium" | "large";

export type PropertyCondition = "excellent" | "good" | "fair" | "overgrown";

export type Frequency = "one-time" | "weekly" | "biweekly" | "monthly";

export interface AddressDetails {
  /** Free-typed text as the user entered it (kept for the mock flow). */
  rawInput: string;
  /** Fully-formed address string, as chosen from suggestions. */
  formatted: string;
  city: string;
  state: string;
  zip: string;
  /**
   * Present only so the data shape already matches what a real geocoding
   * provider (e.g. Google Places) would return. Unused by the mock engine.
   */
  lat?: number;
  lng?: number;
  /** Which provider resolved this address — "mock" today. */
  source: "mock" | "google-places";
}

export interface PropertyDetailsAnswers {
  propertySize: PropertySize | null;
  frontYardSize: PropertySize | null;
  backyardSize: PropertySize | null;
  condition: PropertyCondition | null;
  additionalServiceIds: string[];
  notes?: string;
}

export interface CustomerInfo {
  fullName: string;
  email: string;
  phone: string;
  address: string;
  preferredDate: string;
}

export interface QuoteBreakdownLine {
  label: string;
  amount: number;
}

export interface QuoteBreakdown {
  basePrice: number;
  sizeAdjustment: number;
  conditionAdjustment: number;
  additionalServicesTotal: number;
  additionalServiceLines: QuoteBreakdownLine[];
  subtotal: number;
  discountPercent: number;
  discountAmount: number;
  perVisitTotal: number;
  estimatedMonthlyTotal: number;
  frequency: Frequency;
}

export interface QuoteState {
  serviceId: ServiceId | null;
  address: AddressDetails | null;
  propertyDetails: PropertyDetailsAnswers;
  frequency: Frequency | null;
  customer: CustomerInfo;
}

export interface StoredSubmission extends QuoteState {
  id: string;
  submittedAt: string;
  breakdown: QuoteBreakdown;
}
