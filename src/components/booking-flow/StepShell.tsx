import type { ReactNode } from "react";
import { motion } from "framer-motion";

interface StepShellProps {
  eyebrow?: string;
  title: string;
  subtitle?: string;
  children: ReactNode;
}

export function StepShell({ eyebrow, title, subtitle, children }: StepShellProps) {
  return (
    <motion.div
      initial={{ opacity: 0, x: 24 }}
      animate={{ opacity: 1, x: 0 }}
      exit={{ opacity: 0, x: -24 }}
      transition={{ duration: 0.25, ease: "easeOut" }}
    >
      <div className="mb-6">
        {eyebrow && (
          <span className="mb-2 inline-block text-xs font-bold uppercase tracking-wide text-brand-600">
            {eyebrow}
          </span>
        )}
        <h2 className="text-xl font-bold text-ink-900 sm:text-2xl">{title}</h2>
        {subtitle && <p className="mt-1.5 text-sm text-slate-500">{subtitle}</p>}
      </div>
      {children}
    </motion.div>
  );
}
