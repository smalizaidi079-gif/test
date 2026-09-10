import { StepShell } from "../StepShell";
import { NavigationButtons } from "../NavigationButtons";
import { SelectableCard } from "@/components/ui/SelectableCard";
import { Calendar } from "@/components/ui/icons";
import type { Frequency } from "@/types/booking";
import type { QuoteWizard } from "@/hooks/useQuoteWizard";
import { calculateQuote, formatCurrency } from "@/components/pricing-engine/pricingEngine";
import { FREQUENCY_LABELS } from "@/components/pricing-engine/pricingConfig";

const FREQUENCIES: Frequency[] = ["one-time", "weekly", "biweekly", "monthly"];

export function Step4Frequency({ wizard }: { wizard: QuoteWizard }) {
  return (
    <StepShell
      title="How often do you need this service?"
      subtitle="Recurring plans include an automatic discount."
    >
      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
        {FREQUENCIES.map((freq) => {
          const preview = calculateQuote(
            wizard.state.serviceId,
            wizard.state.propertyDetails,
            freq,
          );
          return (
            <SelectableCard
              key={freq}
              selected={wizard.state.frequency === freq}
              onClick={() => wizard.setFrequency(freq)}
              icon={<Calendar className="h-5 w-5" />}
              title={FREQUENCY_LABELS[freq]}
              description={`${formatCurrency(preview.perVisitTotal)} / visit`}
              badge={preview.discountPercent > 0 ? `Save ${preview.discountPercent * 100}%` : undefined}
            />
          );
        })}
      </div>

      <NavigationButtons onBack={wizard.goBack} onNext={wizard.goNext} disabled={!wizard.canProceed} />
    </StepShell>
  );
}
