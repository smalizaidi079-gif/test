import { StepShell } from "../StepShell";
import { NavigationButtons } from "../NavigationButtons";
import { SelectableCard } from "@/components/ui/SelectableCard";
import { Calendar } from "@/components/ui/icons";
import type { Frequency } from "@/types/booking";
import type { QuoteWizard } from "@/hooks/useQuoteWizard";
import { FREQUENCY_DISCOUNTS, FREQUENCY_LABELS } from "@/components/pricing-engine/pricingConfig";

const FREQUENCIES: Frequency[] = ["one-time", "weekly", "biweekly", "monthly"];

// Exact $ pricing isn't known yet at this point in the flow — property
// details (which drive size/condition/add-on adjustments) are collected
// on the next step — so this only communicates the discount shape, not a
// dollar preview. The full animated breakdown shows up on the Quote step.
const FREQUENCY_DESCRIPTIONS: Record<Frequency, string> = {
  "one-time": "A single scheduled visit",
  weekly: "Consistent weekly upkeep",
  biweekly: "Maintained every two weeks",
  monthly: "Light monthly touch-ups",
};

export function Step3Frequency({ wizard }: { wizard: QuoteWizard }) {
  return (
    <StepShell
      title="How often do you need this service?"
      subtitle="Recurring plans include an automatic discount — exact pricing is calculated after a couple more quick questions."
    >
      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
        {FREQUENCIES.map((freq) => {
          const discountPercent = FREQUENCY_DISCOUNTS[freq];
          return (
            <SelectableCard
              key={freq}
              selected={wizard.state.frequency === freq}
              onClick={() => wizard.setFrequency(freq)}
              icon={<Calendar className="h-5 w-5" />}
              title={FREQUENCY_LABELS[freq]}
              description={FREQUENCY_DESCRIPTIONS[freq]}
              badge={discountPercent > 0 ? `Save ${discountPercent * 100}%` : undefined}
            />
          );
        })}
      </div>

      <NavigationButtons onBack={wizard.goBack} onNext={wizard.goNext} disabled={!wizard.canProceed} />
    </StepShell>
  );
}
