import type {
  Frequency,
  PropertyDetailsAnswers,
  QuoteBreakdown,
  QuoteBreakdownLine,
} from "@/types/booking";
import { getAdditionalServicesForService } from "@/data/propertyQuestionsConfig";
import type { ServiceId } from "@/types/booking";
import {
  ADDITIONAL_SERVICE_PRICE,
  BASE_PRICE,
  CONDITION_ADJUSTMENTS,
  FREQUENCY_DISCOUNTS,
  SIZE_ADJUSTMENTS,
  VISITS_PER_MONTH,
} from "./pricingConfig";

const round2 = (value: number) => Math.round(value * 100) / 100;

/**
 * Pure calculation: given the wizard's collected answers, produce a full
 * price breakdown. No React, no side effects — safe to unit test and safe
 * to reuse if this becomes a server-side calculation later.
 */
export function calculateQuote(
  serviceId: ServiceId | null,
  propertyDetails: PropertyDetailsAnswers,
  frequency: Frequency | null,
): QuoteBreakdown {
  const effectiveFrequency: Frequency = frequency ?? "one-time";
  const basePrice = BASE_PRICE;

  const sizeAdjustment = propertyDetails.propertySize
    ? SIZE_ADJUSTMENTS[propertyDetails.propertySize]
    : 0;

  const conditionAdjustment = propertyDetails.condition
    ? CONDITION_ADJUSTMENTS[propertyDetails.condition]
    : 0;

  const availableAdditionalServices = getAdditionalServicesForService(serviceId);
  const additionalServiceLines: QuoteBreakdownLine[] = propertyDetails.additionalServiceIds.map(
    (id) => {
      const service = availableAdditionalServices.find((s) => s.id === id);
      return {
        label: service?.label ?? id,
        amount: ADDITIONAL_SERVICE_PRICE,
      };
    },
  );
  const additionalServicesTotal = additionalServiceLines.length * ADDITIONAL_SERVICE_PRICE;

  const subtotal = basePrice + sizeAdjustment + conditionAdjustment + additionalServicesTotal;

  const discountPercent = FREQUENCY_DISCOUNTS[effectiveFrequency];
  const discountAmount = round2(subtotal * discountPercent);
  const perVisitTotal = round2(subtotal - discountAmount);

  const estimatedMonthlyTotal = round2(perVisitTotal * VISITS_PER_MONTH[effectiveFrequency]);

  return {
    basePrice,
    sizeAdjustment,
    conditionAdjustment,
    additionalServicesTotal,
    additionalServiceLines,
    subtotal: round2(subtotal),
    discountPercent,
    discountAmount,
    perVisitTotal,
    estimatedMonthlyTotal,
    frequency: effectiveFrequency,
  };
}

export function formatCurrency(amount: number): string {
  return new Intl.NumberFormat("en-US", {
    style: "currency",
    currency: "USD",
    minimumFractionDigits: amount % 1 === 0 ? 0 : 2,
    maximumFractionDigits: 2,
  }).format(amount);
}
