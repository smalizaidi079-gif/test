import { useState } from "react";
import { StepShell } from "../StepShell";
import { NavigationButtons } from "../NavigationButtons";
import { AddressAutocomplete } from "@/components/address-autocomplete";
import type { QuoteWizard } from "@/hooks/useQuoteWizard";
import { CheckCircle2, MapPin } from "@/components/ui/icons";

export function Step2Address({ wizard }: { wizard: QuoteWizard }) {
  const [inputValue, setInputValue] = useState(wizard.state.address?.formatted ?? "");

  return (
    <StepShell
      title="Where's the property located?"
      subtitle="Start typing and choose your address from the suggestions."
    >
      <AddressAutocomplete
        value={inputValue}
        onChange={setInputValue}
        onSelect={(address) => wizard.setAddress(address)}
        placeholder="e.g. 123 Main Street"
      />

      {wizard.state.address && (
        <div className="mt-4 flex items-start gap-3 rounded-2xl border border-brand-100 bg-brand-50 p-4">
          <CheckCircle2 className="mt-0.5 h-5 w-5 shrink-0 text-brand-600" />
          <div>
            <p className="text-sm font-semibold text-brand-800">Address confirmed</p>
            <p className="flex items-center gap-1.5 text-sm text-brand-700">
              <MapPin className="h-3.5 w-3.5" />
              {wizard.state.address.formatted}
            </p>
          </div>
        </div>
      )}

      <NavigationButtons onBack={wizard.goBack} onNext={wizard.goNext} disabled={!wizard.canProceed} />
    </StepShell>
  );
}
