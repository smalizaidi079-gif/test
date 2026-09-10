import { ShieldCheck, Star, Clock } from "./icons";

const BADGES = [
  { icon: Star, label: "4.9/5 from 2,300+ bookings" },
  { icon: ShieldCheck, label: "Licensed & insured pros" },
  { icon: Clock, label: "Book in under 2 minutes" },
];

export function TrustBadges({ className = "" }: { className?: string }) {
  return (
    <div className={`flex flex-wrap items-center justify-center gap-x-6 gap-y-2 ${className}`}>
      {BADGES.map(({ icon: Icon, label }) => (
        <div key={label} className="flex items-center gap-1.5 text-xs font-medium text-slate-500">
          <Icon className="h-3.5 w-3.5 text-brand-500" />
          {label}
        </div>
      ))}
    </div>
  );
}
