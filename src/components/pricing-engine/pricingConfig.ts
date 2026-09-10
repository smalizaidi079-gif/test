import type { Frequency, PropertyCondition, PropertySize } from "@/types/booking";

// All demo pricing knobs live here so the client's real numbers can be
// dropped in later without touching calculation logic in pricingEngine.ts.

export const BASE_PRICE = 50;

export const SIZE_ADJUSTMENTS: Record<PropertySize, number> = {
  small: 0,
  medium: 20,
  large: 40,
};

export const CONDITION_ADJUSTMENTS: Record<PropertyCondition, number> = {
  excellent: 0,
  good: 0,
  fair: 10,
  overgrown: 25,
};

export const ADDITIONAL_SERVICE_PRICE = 10;

export const FREQUENCY_DISCOUNTS: Record<Frequency, number> = {
  "one-time": 0,
  weekly: 0.1,
  biweekly: 0.05,
  monthly: 0,
};

// Multiplier used to project a recurring per-visit price into an
// "estimated monthly" figure for the confirmation screen.
export const VISITS_PER_MONTH: Record<Frequency, number> = {
  "one-time": 1,
  weekly: 4.33,
  biweekly: 2.17,
  monthly: 1,
};

export const FREQUENCY_LABELS: Record<Frequency, string> = {
  "one-time": "One time",
  weekly: "Weekly",
  biweekly: "Every 2 weeks",
  monthly: "Monthly",
};
