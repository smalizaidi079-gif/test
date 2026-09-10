import { motion } from "framer-motion";
import { StepShell } from "../StepShell";
import { NavigationButtons } from "../NavigationButtons";
import { AnimatedNumber } from "@/components/ui/AnimatedNumber";
import { formatCurrency } from "@/components/pricing-engine/pricingEngine";
import { FREQUENCY_LABELS } from "@/components/pricing-engine/pricingConfig";
import type { QuoteWizard } from "@/hooks/useQuoteWizard";
import { getServiceById } from "@/data/servicesConfig";

export function Step5Quote({ wizard }: { wizard: QuoteWizard }) {
  const { breakdown, state } = wizard;
  const service = getServiceById(state.serviceId);
  const isRecurring = breakdown.frequency !== "one-time";

  return (
    <StepShell
      eyebrow="Your instant estimate"
      title="Here's your instant quote"
      subtitle={`For ${service?.title.toLowerCase()} — ${FREQUENCY_LABELS[breakdown.frequency].toLowerCase()}.`}
    >
      <div className="overflow-hidden rounded-3xl border border-brand-100 bg-gradient-to-br from-brand-500 to-brand-600 p-6 text-white shadow-card-lg sm:p-8">
        <p className="text-sm font-medium text-brand-100">
          {isRecurring ? "Price per visit" : "Total price"}
        </p>
        <div className="mt-1 flex items-end gap-2">
          <span className="text-5xl font-extrabold tabular-nums sm:text-6xl">
            <AnimatedNumber
              value={breakdown.perVisitTotal}
              formatter={(n) => formatCurrency(n)}
            />
          </span>
        </div>
        {isRecurring && (
          <p className="mt-2 text-sm text-brand-100">
            ≈ <AnimatedNumber value={breakdown.estimatedMonthlyTotal} formatter={formatCurrency} />{" "}
            estimated per month
          </p>
        )}
      </div>

      <motion.div
        initial={{ opacity: 0, y: 8 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ delay: 0.15 }}
        className="mt-5 rounded-2xl border border-slate-100 bg-white p-5 shadow-card"
      >
        <p className="mb-3 text-sm font-semibold text-ink-900">Price breakdown</p>
        <div className="space-y-2 text-sm">
          <Row label="Base price" amount={breakdown.basePrice} />
          {breakdown.sizeAdjustment > 0 && (
            <Row label="Property size adjustment" amount={breakdown.sizeAdjustment} />
          )}
          {breakdown.conditionAdjustment > 0 && (
            <Row label="Condition adjustment" amount={breakdown.conditionAdjustment} />
          )}
          {breakdown.additionalServiceLines.map((line) => (
            <Row key={line.label} label={line.label} amount={line.amount} />
          ))}
          <div className="my-2 border-t border-dashed border-slate-200" />
          <Row label="Subtotal" amount={breakdown.subtotal} bold />
          {breakdown.discountAmount > 0 && (
            <Row
              label={`${FREQUENCY_LABELS[breakdown.frequency]} discount (${breakdown.discountPercent * 100}%)`}
              amount={-breakdown.discountAmount}
              highlight
            />
          )}
          <div className="my-2 border-t border-slate-200" />
          <Row label={isRecurring ? "Price per visit" : "Total"} amount={breakdown.perVisitTotal} bold large />
        </div>
      </motion.div>

      <p className="mt-4 text-center text-xs text-slate-400">
        This is a demo estimate. Final pricing is confirmed by a local pro.
      </p>

      <NavigationButtons onBack={wizard.goBack} onNext={wizard.goNext} nextLabel="Continue to booking" />
    </StepShell>
  );
}

function Row({
  label,
  amount,
  bold,
  large,
  highlight,
}: {
  label: string;
  amount: number;
  bold?: boolean;
  large?: boolean;
  highlight?: boolean;
}) {
  const isNegative = amount < 0;
  return (
    <div className="flex items-center justify-between">
      <span className={`${bold ? "font-semibold text-ink-900" : "text-slate-500"}`}>{label}</span>
      <span
        className={`tabular-nums ${large ? "text-lg" : ""} ${
          highlight ? "text-brand-600 font-semibold" : bold ? "font-semibold text-ink-900" : "text-ink-800"
        }`}
      >
        {isNegative ? "-" : ""}
        {formatCurrency(Math.abs(amount))}
      </span>
    </div>
  );
}
