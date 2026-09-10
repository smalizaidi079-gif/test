import { AnimatePresence } from "framer-motion";
import { useQuoteWizard } from "@/hooks/useQuoteWizard";
import { ProgressBar } from "./ProgressBar";
import { TrustBadges } from "@/components/ui/TrustBadges";
import { Step1Service } from "./steps/Step1Service";
import { Step2Address } from "./steps/Step2Address";
import { Step3PropertyDetails } from "./steps/Step3PropertyDetails";
import { Step4Frequency } from "./steps/Step4Frequency";
import { Step5Quote } from "./steps/Step5Quote";
import { Step6CustomerInfo } from "./steps/Step6CustomerInfo";
import { Step7Confirmation } from "./steps/Step7Confirmation";
import { Leaf } from "@/components/ui/icons";

export function QuoteWidget() {
  const wizard = useQuoteWizard();

  return (
    <div id="iq-widget-scope" className="w-full font-sans">
      <div className="mx-auto w-full max-w-2xl">
        <div className="overflow-hidden rounded-[28px] border border-slate-100 bg-white shadow-card-lg">
          <div className="border-b border-slate-100 bg-gradient-to-r from-brand-50 via-white to-white px-6 py-5 sm:px-8">
            <div className="flex items-center gap-2.5">
              <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-brand-500 text-white">
                <Leaf className="h-5 w-5" />
              </div>
              <div>
                <p className="text-sm font-bold leading-none text-ink-900">GreenPro Estimate</p>
                <p className="mt-0.5 text-[11px] text-slate-400">Instant quote & booking</p>
              </div>
            </div>
          </div>

          <div className="px-6 pt-6 sm:px-8">
            <ProgressBar currentStep={wizard.step} />
          </div>

          <div className="px-6 py-7 sm:px-8">
            <AnimatePresence mode="wait">
              {wizard.step === 1 && <Step1Service key="1" wizard={wizard} />}
              {wizard.step === 2 && <Step2Address key="2" wizard={wizard} />}
              {wizard.step === 3 && <Step3PropertyDetails key="3" wizard={wizard} />}
              {wizard.step === 4 && <Step4Frequency key="4" wizard={wizard} />}
              {wizard.step === 5 && <Step5Quote key="5" wizard={wizard} />}
              {wizard.step === 6 && <Step6CustomerInfo key="6" wizard={wizard} />}
              {wizard.step === 7 && <Step7Confirmation key="7" wizard={wizard} />}
            </AnimatePresence>
          </div>
        </div>

        <TrustBadges className="mt-5" />

        <p className="mt-4 text-center text-[11px] text-slate-400">
          Prototype for client demo · Not connected to live payments or scheduling
        </p>
      </div>
    </div>
  );
}
