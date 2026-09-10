import { StepShell } from "../StepShell";
import { NavigationButtons } from "../NavigationButtons";
import { TextField } from "@/components/ui/TextField";
import { Calendar, Mail, MapPin, Phone, User } from "@/components/ui/icons";
import type { QuoteWizard } from "@/hooks/useQuoteWizard";

export function Step6CustomerInfo({ wizard }: { wizard: QuoteWizard }) {
  const { customer } = wizard.state;
  const today = new Date().toISOString().split("T")[0];

  return (
    <StepShell
      title="Almost there — how do we reach you?"
      subtitle="We'll send your confirmed quote and booking details here."
    >
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <TextField
          label="Full name"
          icon={<User className="h-4 w-4" />}
          value={customer.fullName}
          onChange={(e) => wizard.updateCustomer({ fullName: e.target.value })}
          placeholder="Jane Doe"
        />
        <TextField
          label="Email address"
          type="email"
          icon={<Mail className="h-4 w-4" />}
          value={customer.email}
          onChange={(e) => wizard.updateCustomer({ email: e.target.value })}
          placeholder="jane@example.com"
        />
        <TextField
          label="Phone number"
          type="tel"
          icon={<Phone className="h-4 w-4" />}
          value={customer.phone}
          onChange={(e) => wizard.updateCustomer({ phone: e.target.value })}
          placeholder="(555) 123-4567"
        />
        <TextField
          label="Preferred date"
          type="date"
          min={today}
          icon={<Calendar className="h-4 w-4" />}
          value={customer.preferredDate}
          onChange={(e) => wizard.updateCustomer({ preferredDate: e.target.value })}
        />
        <div className="sm:col-span-2">
          <TextField
            label="Service address"
            icon={<MapPin className="h-4 w-4" />}
            value={customer.address}
            onChange={(e) => wizard.updateCustomer({ address: e.target.value })}
          />
        </div>
      </div>

      <p className="mt-4 text-xs text-slate-400">
        Demo mode: no payment is collected and your info is stored only in this browser.
      </p>

      <NavigationButtons
        onBack={wizard.goBack}
        onNext={wizard.submit}
        nextLabel="Get my confirmed quote"
        disabled={!wizard.canProceed}
      />
    </StepShell>
  );
}
