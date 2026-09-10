import { motion } from "framer-motion";
import { Check } from "@/components/ui/icons";
import { STEP_COUNT, STEP_LABELS } from "@/hooks/useQuoteWizard";

interface ProgressBarProps {
  currentStep: number;
}

export function ProgressBar({ currentStep }: ProgressBarProps) {
  const percent = ((currentStep - 1) / (STEP_COUNT - 1)) * 100;

  return (
    <div className="w-full">
      <div className="mb-3 h-1.5 w-full overflow-hidden rounded-full bg-slate-100">
        <motion.div
          className="h-full rounded-full bg-brand-500"
          initial={false}
          animate={{ width: `${percent}%` }}
          transition={{ duration: 0.4, ease: "easeInOut" }}
        />
      </div>

      <div className="hidden items-center justify-between sm:flex">
        {STEP_LABELS.map((label, idx) => {
          const stepNum = idx + 1;
          const isDone = stepNum < currentStep;
          const isActive = stepNum === currentStep;
          return (
            <div key={label} className="flex flex-1 flex-col items-center gap-1.5">
              <div
                className={`flex h-6 w-6 items-center justify-center rounded-full text-[11px] font-bold transition-colors ${
                  isDone
                    ? "bg-brand-500 text-white"
                    : isActive
                      ? "bg-brand-100 text-brand-700 ring-2 ring-brand-500"
                      : "bg-slate-100 text-slate-400"
                }`}
              >
                {isDone ? <Check className="h-3 w-3" strokeWidth={3} /> : stepNum}
              </div>
              <span
                className={`text-[11px] font-medium ${
                  isActive ? "text-ink-900" : "text-slate-400"
                }`}
              >
                {label}
              </span>
            </div>
          );
        })}
      </div>

      <p className="text-center text-xs font-medium text-slate-400 sm:hidden">
        Step {currentStep} of {STEP_COUNT} — {STEP_LABELS[currentStep - 1]}
      </p>
    </div>
  );
}
