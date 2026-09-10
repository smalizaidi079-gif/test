import { StepShell } from "../StepShell";
import { NavigationButtons } from "../NavigationButtons";
import { SelectableCard } from "@/components/ui/SelectableCard";
import { SERVICES } from "@/data/servicesConfig";
import type { QuoteWizard } from "@/hooks/useQuoteWizard";

export function Step1Service({ wizard }: { wizard: QuoteWizard }) {
  return (
    <StepShell
      eyebrow="Let's get started"
      title="What service do you need?"
      subtitle="Pick the service you'd like an instant quote for."
    >
      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
        {SERVICES.map((service) => {
          const Icon = service.icon;
          return (
            <SelectableCard
              key={service.id}
              selected={wizard.state.serviceId === service.id}
              onClick={() => wizard.setServiceId(service.id)}
              icon={<Icon className="h-5 w-5" />}
              title={service.title}
              description={service.description}
            />
          );
        })}
      </div>

      <NavigationButtons showBack={false} onNext={wizard.goNext} disabled={!wizard.canProceed} />
    </StepShell>
  );
}
