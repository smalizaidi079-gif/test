import type { ServiceId } from "@/types/booking";

export interface AdditionalServiceOption {
  id: string;
  label: string;
}

export interface ServiceQuestionsConfig {
  /** Label shown above the front/backyard size question set. */
  sizeQuestionLabel: string;
  showFrontBackSplit: boolean;
  conditionLabel: string;
  additionalServices: AdditionalServiceOption[];
  notesPlaceholder?: string;
}

// Drives Step 3 (Property Details): which questions render, and which
// additional-service checkboxes are offered, per selected service.
export const PROPERTY_QUESTIONS: Record<ServiceId, ServiceQuestionsConfig> = {
  "lawn-mowing": {
    sizeQuestionLabel: "What's your total property size?",
    showFrontBackSplit: true,
    conditionLabel: "How would you describe the lawn's current condition?",
    additionalServices: [
      { id: "edging", label: "Edging" },
      { id: "weed-removal", label: "Weed removal" },
      { id: "leaf-cleanup", label: "Leaf clean-up" },
      { id: "fertilization", label: "Fertilization" },
    ],
  },
  landscaping: {
    sizeQuestionLabel: "What's the size of the area to landscape?",
    showFrontBackSplit: false,
    conditionLabel: "How would you describe the current state of the space?",
    additionalServices: [
      { id: "mulching", label: "Mulching" },
      { id: "planting", label: "New planting" },
      { id: "hardscape", label: "Hardscape / pavers" },
      { id: "irrigation", label: "Irrigation check" },
    ],
  },
  "garden-maintenance": {
    sizeQuestionLabel: "What's your garden size?",
    showFrontBackSplit: false,
    conditionLabel: "How would you describe the garden's current condition?",
    additionalServices: [
      { id: "weeding", label: "Weeding" },
      { id: "pruning", label: "Pruning & trimming" },
      { id: "mulch-refresh", label: "Mulch refresh" },
      { id: "pest-check", label: "Pest inspection" },
    ],
  },
  cleaning: {
    sizeQuestionLabel: "What's the size of the outdoor space to clean?",
    showFrontBackSplit: false,
    conditionLabel: "How would you describe the current condition?",
    additionalServices: [
      { id: "window-cleaning", label: "Exterior window cleaning" },
      { id: "pressure-washing", label: "Pressure washing" },
      { id: "gutter-cleaning", label: "Gutter clean-out" },
      { id: "patio-cleaning", label: "Patio / deck cleaning" },
    ],
  },
  other: {
    sizeQuestionLabel: "What's the approximate property size?",
    showFrontBackSplit: false,
    conditionLabel: "How would you describe the current condition?",
    additionalServices: [
      { id: "consultation", label: "On-site consultation" },
      { id: "cleanup", label: "General clean-up" },
      { id: "haul-away", label: "Debris haul-away" },
    ],
    notesPlaceholder: "Tell us a bit more about what you need…",
  },
};

export function getQuestionsForService(serviceId: ServiceId | null): ServiceQuestionsConfig {
  return PROPERTY_QUESTIONS[serviceId ?? "lawn-mowing"];
}

export function getAdditionalServicesForService(
  serviceId: ServiceId | null,
): AdditionalServiceOption[] {
  return getQuestionsForService(serviceId).additionalServices;
}
