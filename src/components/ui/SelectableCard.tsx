import type { ReactNode } from "react";
import { motion } from "framer-motion";
import { CheckCircle2 } from "./icons";

interface SelectableCardProps {
  selected: boolean;
  onClick: () => void;
  icon?: ReactNode;
  title: string;
  description?: string;
  badge?: string;
  layout?: "vertical" | "horizontal";
}

export function SelectableCard({
  selected,
  onClick,
  icon,
  title,
  description,
  badge,
  layout = "vertical",
}: SelectableCardProps) {
  return (
    <motion.button
      type="button"
      onClick={onClick}
      whileTap={{ scale: 0.98 }}
      className={`group relative w-full overflow-hidden rounded-2xl border-2 bg-white p-4 text-left transition-all duration-150 ${
        selected
          ? "border-brand-500 shadow-glow"
          : "border-slate-100 shadow-card hover:border-slate-200 hover:shadow-card-lg"
      } ${layout === "horizontal" ? "flex items-center gap-4" : "flex flex-col gap-3"}`}
    >
      {badge && (
        <span className="absolute right-3 top-3 rounded-full bg-brand-50 px-2.5 py-1 text-[11px] font-semibold text-brand-700">
          {badge}
        </span>
      )}

      {icon && (
        <div
          className={`flex h-11 w-11 shrink-0 items-center justify-center rounded-xl transition-colors ${
            selected ? "bg-brand-500 text-white" : "bg-brand-50 text-brand-600"
          }`}
        >
          {icon}
        </div>
      )}

      <div className="flex-1">
        <div className="flex items-center gap-2">
          <span className="font-semibold text-ink-900">{title}</span>
          {selected && <CheckCircle2 className="h-4 w-4 text-brand-500" />}
        </div>
        {description && <p className="mt-0.5 text-sm text-slate-500">{description}</p>}
      </div>
    </motion.button>
  );
}
