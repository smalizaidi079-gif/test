import { StepShell } from "../StepShell";
import { NavigationButtons } from "../NavigationButtons";
import { SelectableCard } from "@/components/ui/SelectableCard";
import { CheckboxPill } from "@/components/ui/CheckboxPill";
import { getQuestionsForService } from "@/data/propertyQuestionsConfig";
import type { PropertyCondition, PropertySize } from "@/types/booking";
import type { QuoteWizard } from "@/hooks/useQuoteWizard";

const SIZE_OPTIONS: { id: PropertySize; title: string; description: string }[] = [
  { id: "small", title: "Small", description: "Up to 3,000 sq ft" },
  { id: "medium", title: "Medium", description: "3,000–8,000 sq ft" },
  { id: "large", title: "Large", description: "8,000+ sq ft" },
];

const CONDITION_OPTIONS: { id: PropertyCondition; title: string; description: string }[] = [
  { id: "excellent", title: "Excellent", description: "Well maintained" },
  { id: "good", title: "Good", description: "Minor upkeep needed" },
  { id: "fair", title: "Fair", description: "Some overgrowth" },
  { id: "overgrown", title: "Overgrown", description: "Needs real attention" },
];

function SizeGrid({
  value,
  onChange,
}: {
  value: PropertySize | null;
  onChange: (v: PropertySize) => void;
}) {
  return (
    <div className="grid grid-cols-3 gap-2.5">
      {SIZE_OPTIONS.map((opt) => (
        <SelectableCard
          key={opt.id}
          selected={value === opt.id}
          onClick={() => onChange(opt.id)}
          title={opt.title}
          description={opt.description}
        />
      ))}
    </div>
  );
}

export function Step3PropertyDetails({ wizard }: { wizard: QuoteWizard }) {
  const config = getQuestionsForService(wizard.state.serviceId);
  const details = wizard.state.propertyDetails;

  return (
    <StepShell
      title="Tell us about the property"
      subtitle="A few quick details help us build an accurate quote."
    >
      <div className="space-y-7">
        <div>
          <p className="mb-3 text-sm font-semibold text-ink-800">{config.sizeQuestionLabel}</p>
          <SizeGrid
            value={details.propertySize}
            onChange={(v) => wizard.updatePropertyDetails({ propertySize: v })}
          />
        </div>

        {config.showFrontBackSplit && (
          <>
            <div>
              <p className="mb-3 text-sm font-semibold text-ink-800">Front yard size</p>
              <SizeGrid
                value={details.frontYardSize}
                onChange={(v) => wizard.updatePropertyDetails({ frontYardSize: v })}
              />
            </div>
            <div>
              <p className="mb-3 text-sm font-semibold text-ink-800">Backyard size</p>
              <SizeGrid
                value={details.backyardSize}
                onChange={(v) => wizard.updatePropertyDetails({ backyardSize: v })}
              />
            </div>
          </>
        )}

        <div>
          <p className="mb-3 text-sm font-semibold text-ink-800">{config.conditionLabel}</p>
          <div className="grid grid-cols-2 gap-2.5 sm:grid-cols-4">
            {CONDITION_OPTIONS.map((opt) => (
              <SelectableCard
                key={opt.id}
                selected={details.condition === opt.id}
                onClick={() => wizard.updatePropertyDetails({ condition: opt.id })}
                title={opt.title}
                description={opt.description}
              />
            ))}
          </div>
        </div>

        <div>
          <p className="mb-3 text-sm font-semibold text-ink-800">
            Any additional services? <span className="font-normal text-slate-400">(optional)</span>
          </p>
          <div className="grid grid-cols-1 gap-2.5 sm:grid-cols-2">
            {config.additionalServices.map((service) => (
              <CheckboxPill
                key={service.id}
                label={service.label}
                priceLabel="+$10"
                checked={details.additionalServiceIds.includes(service.id)}
                onToggle={() => wizard.toggleAdditionalService(service.id)}
              />
            ))}
          </div>
        </div>

        {config.notesPlaceholder && (
          <div>
            <p className="mb-3 text-sm font-semibold text-ink-800">Anything else we should know?</p>
            <textarea
              value={details.notes}
              onChange={(e) => wizard.updatePropertyDetails({ notes: e.target.value })}
              placeholder={config.notesPlaceholder}
              rows={3}
              className="w-full rounded-xl border border-slate-200 p-3.5 text-sm outline-none transition focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15"
            />
          </div>
        )}
      </div>

      <NavigationButtons onBack={wizard.goBack} onNext={wizard.goNext} disabled={!wizard.canProceed} />
    </StepShell>
  );
}
