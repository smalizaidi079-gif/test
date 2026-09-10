import type { ServiceId } from "@/types/booking";
import { Flower, Leaf, Grid, Scissors, Sparkles } from "@/components/ui/icons";

export interface ServiceOption {
  id: ServiceId;
  title: string;
  description: string;
  icon: typeof Leaf;
}

export const SERVICES: ServiceOption[] = [
  {
    id: "lawn-mowing",
    title: "Lawn Mowing",
    description: "Regular mowing, edging & clean-up for a tidy lawn.",
    icon: Leaf,
  },
  {
    id: "landscaping",
    title: "Landscaping",
    description: "Design, planting & hardscape improvements.",
    icon: Flower,
  },
  {
    id: "garden-maintenance",
    title: "Garden Maintenance",
    description: "Pruning, weeding & seasonal garden upkeep.",
    icon: Scissors,
  },
  {
    id: "cleaning",
    title: "Cleaning",
    description: "Exterior & outdoor space cleaning services.",
    icon: Sparkles,
  },
  {
    id: "other",
    title: "Other",
    description: "Something else in mind? Tell us more.",
    icon: Grid,
  },
];

export function getServiceById(id: ServiceId | null): ServiceOption | undefined {
  return SERVICES.find((s) => s.id === id);
}
