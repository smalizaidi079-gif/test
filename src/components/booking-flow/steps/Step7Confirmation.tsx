import type { ReactNode } from "react";
import { motion } from "framer-motion";
import { CheckCircle2, Calendar, Leaf, Mail, MapPin, Phone, User } from "@/components/ui/icons";
import { Button } from "@/components/ui/Button";
import { formatCurrency } from "@/components/pricing-engine/pricingEngine";
import { FREQUENCY_LABELS } from "@/components/pricing-engine/pricingConfig";
import { getServiceById } from "@/data/servicesConfig";
import type { QuoteWizard } from "@/hooks/useQuoteWizard";

export function Step7Confirmation({ wizard }: { wizard: QuoteWizard }) {
  const submission = wizard.submission;
  if (!submission) return null;

  const service = getServiceById(submission.serviceId);
  const isRecurring = submission.breakdown.frequency !== "one-time";
  const ServiceIcon = service?.icon ?? Leaf;

  return (
    <motion.div initial={{ opacity: 0, y: 12 }} animate={{ opacity: 1, y: 0 }}>
      <div className="flex flex-col items-center text-center">
        <motion.div
          initial={{ scale: 0.6, opacity: 0 }}
          animate={{ scale: 1, opacity: 1 }}
          transition={{ type: "spring", stiffness: 260, damping: 18 }}
          className="flex h-16 w-16 items-center justify-center rounded-full bg-brand-500 text-white shadow-glow"
        >
          <CheckCircle2 className="h-8 w-8" />
        </motion.div>
        <h2 className="mt-4 text-2xl font-bold text-ink-900">You're all set, {firstName(submission.customer.fullName)}!</h2>
        <p className="mt-1.5 max-w-sm text-sm text-slate-500">
          Your estimated quote has been saved. A local pro will reach out to confirm your booking.
        </p>
      </div>

      <div className="mt-6 rounded-3xl border border-brand-100 bg-gradient-to-br from-brand-500 to-brand-600 p-6 text-center text-white shadow-card-lg">
        <p className="text-sm font-medium text-brand-100">
          {isRecurring ? "Estimated monthly price" : "Your estimated quote"}
        </p>
        <p className="mt-1 text-4xl font-extrabold tabular-nums sm:text-5xl">
          {formatCurrency(isRecurring ? submission.breakdown.estimatedMonthlyTotal : submission.breakdown.perVisitTotal)}
        </p>
        {isRecurring && (
          <p className="mt-1 text-sm text-brand-100">
            {formatCurrency(submission.breakdown.perVisitTotal)} per visit ·{" "}
            {FREQUENCY_LABELS[submission.breakdown.frequency]}
          </p>
        )}
      </div>

      <div className="mt-5 space-y-3 rounded-2xl border border-slate-100 bg-white p-5 shadow-card">
        <p className="text-sm font-semibold text-ink-900">Booking summary</p>

        <SummaryRow icon={<ServiceIcon className="h-4 w-4" />} label={service?.title ?? "Service"} />
        <SummaryRow icon={<MapPin className="h-4 w-4" />} label={submission.customer.address} />
        <SummaryRow
          icon={<Calendar className="h-4 w-4" />}
          label={`${FREQUENCY_LABELS[submission.breakdown.frequency]} · Preferred date ${formatDate(
            submission.customer.preferredDate,
          )}`}
        />
        <div className="my-1 border-t border-slate-100" />
        <SummaryRow icon={<User className="h-4 w-4" />} label={submission.customer.fullName} />
        <SummaryRow icon={<Mail className="h-4 w-4" />} label={submission.customer.email} />
        <SummaryRow icon={<Phone className="h-4 w-4" />} label={submission.customer.phone} />
      </div>

      <div className="mt-6 space-y-3">
        <Button fullWidth onClick={() => alert("Demo only — this is where booking confirmation / payment would go live.")}>
          Confirm my booking
        </Button>
        <Button variant="secondary" fullWidth onClick={wizard.reset}>
          Start a new quote
        </Button>
      </div>
    </motion.div>
  );
}

function SummaryRow({ icon, label }: { icon: ReactNode; label: string }) {
  return (
    <div className="flex items-center gap-2.5 text-sm text-ink-700">
      <span className="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-brand-600">
        {icon}
      </span>
      <span className="truncate">{label}</span>
    </div>
  );
}

function firstName(fullName: string) {
  return fullName.trim().split(" ")[0] || "there";
}

function formatDate(iso: string) {
  if (!iso) return "TBD";
  const date = new Date(`${iso}T00:00:00`);
  return date.toLocaleDateString("en-US", { month: "short", day: "numeric", year: "numeric" });
}
