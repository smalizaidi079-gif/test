import { motion } from "framer-motion";
import { Check } from "./icons";

interface CheckboxPillProps {
  label: string;
  checked: boolean;
  onToggle: () => void;
  priceLabel?: string;
}

export function CheckboxPill({ label, checked, onToggle, priceLabel }: CheckboxPillProps) {
  return (
    <motion.button
      type="button"
      whileTap={{ scale: 0.97 }}
      onClick={onToggle}
      className={`flex w-full items-center justify-between gap-3 rounded-xl border-2 px-4 py-3 text-left text-sm font-medium transition-colors ${
        checked
          ? "border-brand-500 bg-brand-50 text-brand-800"
          : "border-slate-100 bg-white text-ink-800 hover:border-slate-200"
      }`}
    >
      <span className="flex items-center gap-2.5">
        <span
          className={`flex h-5 w-5 shrink-0 items-center justify-center rounded-md border-2 transition-colors ${
            checked ? "border-brand-500 bg-brand-500" : "border-slate-300 bg-white"
          }`}
        >
          {checked && <Check className="h-3.5 w-3.5 text-white" strokeWidth={3} />}
        </span>
        {label}
      </span>
      {priceLabel && (
        <span className={`text-xs font-semibold ${checked ? "text-brand-600" : "text-slate-400"}`}>
          {priceLabel}
        </span>
      )}
    </motion.button>
  );
}
