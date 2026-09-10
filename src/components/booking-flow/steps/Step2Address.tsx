import { useState } from "react";
import { StepShell } from "../StepShell";
import { NavigationButtons } from "../NavigationButtons";
import { AddressAutocomplete, SatelliteMap } from "@/components/address-autocomplete";
import type { QuoteWizard } from "@/hooks/useQuoteWizard";
import { CheckCircle2, MapPin } from "@/components/ui/icons";

export function Step2Address({ wizard }: { wizard: QuoteWizard }) {
  const [inputValue, setInputValue] = useState(wizard.state.address?.formatted ?? "");
  const address = wizard.state.address;

  return (
    <StepShell
      title="Where's the property located?"
      subtitle="Start typing and choose your address from the suggestions."
    >
      <AddressAutocomplete
        value={inputValue}
        onChange={setInputValue}
        onSelect={(details) => wizard.setAddress(details)}
        placeholder="e.g. 123 Main Street"
      />

      {address && (
        <>
          <div className="mt-4 flex items-start gap-3 rounded-2xl border border-brand-100 bg-brand-50 p-4">
            <CheckCircle2 className="mt-0.5 h-5 w-5 shrink-0 text-brand-600" />
            <div>
              <p className="text-sm font-semibold text-brand-800">Address confirmed</p>
              <p className="flex items-center gap-1.5 text-sm text-brand-700">
                <MapPin className="h-3.5 w-3.5" />
                {address.formatted}
              </p>
            </div>
          </div>

          <SatelliteMap lat={address.lat} lng={address.lng} label={address.formatted} />

          {address.lat !== undefined && address.lng !== undefined && (
            <div className="mt-3 grid grid-cols-2 gap-2 text-xs text-slate-500 sm:grid-cols-4">
              <DetailChip label="City" value={address.city || "—"} />
              <DetailChip label="State" value={address.state || "—"} />
              <DetailChip label="ZIP" value={address.zip || "—"} />
              <DetailChip label="Coordinates" value={`${address.lat.toFixed(4)}, ${address.lng.toFixed(4)}`} />
            </div>
          )}
        </>
      )}

      <NavigationButtons onBack={wizard.goBack} onNext={wizard.goNext} disabled={!wizard.canProceed} />
    </StepShell>
  );
}

function DetailChip({ label, value }: { label: string; value: string }) {
  return (
    <div className="rounded-lg bg-slate-50 px-2.5 py-2">
      <p className="text-[10px] font-semibold uppercase tracking-wide text-slate-400">{label}</p>
      <p className="mt-0.5 truncate font-medium text-ink-800">{value}</p>
    </div>
  );
}
