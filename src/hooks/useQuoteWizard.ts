import { useMemo, useState } from "react";
import type {
  AddressDetails,
  CustomerInfo,
  Frequency,
  PropertyDetailsAnswers,
  QuoteState,
  ServiceId,
  StoredSubmission,
} from "@/types/booking";
import { calculateQuote } from "@/components/pricing-engine/pricingEngine";

export const STEP_COUNT = 7;

export const STEP_LABELS = [
  "Service",
  "Address",
  "Details",
  "Frequency",
  "Quote",
  "Your info",
  "Done",
] as const;

const STORAGE_KEY = "instant_quote_submissions";

function createInitialState(): QuoteState {
  return {
    serviceId: null,
    address: null,
    propertyDetails: {
      propertySize: null,
      frontYardSize: null,
      backyardSize: null,
      condition: null,
      additionalServiceIds: [],
      notes: "",
    },
    frequency: null,
    customer: {
      fullName: "",
      email: "",
      phone: "",
      address: "",
      preferredDate: "",
    },
  };
}

export function useQuoteWizard() {
  const [step, setStep] = useState(1);
  const [state, setState] = useState<QuoteState>(createInitialState());
  const [submission, setSubmission] = useState<StoredSubmission | null>(null);

  const breakdown = useMemo(
    () => calculateQuote(state.serviceId, state.propertyDetails, state.frequency),
    [state.serviceId, state.propertyDetails, state.frequency],
  );

  function setServiceId(serviceId: ServiceId) {
    setState((prev) => ({ ...prev, serviceId }));
  }

  function setAddress(address: AddressDetails) {
    setState((prev) => ({
      ...prev,
      address,
      customer: { ...prev.customer, address: address.formatted },
    }));
  }

  function setAddressRaw(rawInput: string) {
    setState((prev) => ({
      ...prev,
      address: prev.address ? { ...prev.address, rawInput } : null,
    }));
  }

  function updatePropertyDetails(patch: Partial<PropertyDetailsAnswers>) {
    setState((prev) => ({
      ...prev,
      propertyDetails: { ...prev.propertyDetails, ...patch },
    }));
  }

  function toggleAdditionalService(id: string) {
    setState((prev) => {
      const current = prev.propertyDetails.additionalServiceIds;
      const next = current.includes(id)
        ? current.filter((s) => s !== id)
        : [...current, id];
      return {
        ...prev,
        propertyDetails: { ...prev.propertyDetails, additionalServiceIds: next },
      };
    });
  }

  function setFrequency(frequency: Frequency) {
    setState((prev) => ({ ...prev, frequency }));
  }

  function updateCustomer(patch: Partial<CustomerInfo>) {
    setState((prev) => ({ ...prev, customer: { ...prev.customer, ...patch } }));
  }

  function goNext() {
    setStep((s) => Math.min(STEP_COUNT, s + 1));
  }

  function goBack() {
    setStep((s) => Math.max(1, s - 1));
  }

  function submit() {
    const record: StoredSubmission = {
      ...state,
      id: `quote_${Date.now()}`,
      submittedAt: new Date().toISOString(),
      breakdown,
    };

    try {
      const existingRaw = window.localStorage.getItem(STORAGE_KEY);
      const existing: StoredSubmission[] = existingRaw ? JSON.parse(existingRaw) : [];
      window.localStorage.setItem(STORAGE_KEY, JSON.stringify([...existing, record]));
    } catch {
      // localStorage may be unavailable (private browsing, sandboxed iframe).
      // The demo still proceeds to the confirmation screen either way.
    }

    setSubmission(record);
    goNext();
  }

  function reset() {
    setState(createInitialState());
    setSubmission(null);
    setStep(1);
  }

  const canProceed = useMemo(() => {
    switch (step) {
      case 1:
        return state.serviceId !== null;
      case 2:
        return state.address !== null;
      case 3:
        return state.propertyDetails.propertySize !== null && state.propertyDetails.condition !== null;
      case 4:
        return state.frequency !== null;
      case 5:
        return true;
      case 6:
        return (
          state.customer.fullName.trim().length > 1 &&
          /^\S+@\S+\.\S+$/.test(state.customer.email) &&
          state.customer.phone.trim().length >= 7 &&
          state.customer.preferredDate.trim().length > 0
        );
      default:
        return true;
    }
  }, [step, state]);

  return {
    step,
    setStep,
    state,
    breakdown,
    submission,
    canProceed,
    setServiceId,
    setAddress,
    setAddressRaw,
    updatePropertyDetails,
    toggleAdditionalService,
    setFrequency,
    updateCustomer,
    goNext,
    goBack,
    submit,
    reset,
  };
}

export type QuoteWizard = ReturnType<typeof useQuoteWizard>;
