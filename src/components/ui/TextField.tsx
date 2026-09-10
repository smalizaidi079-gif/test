import type { InputHTMLAttributes, ReactNode } from "react";

interface TextFieldProps extends InputHTMLAttributes<HTMLInputElement> {
  label: string;
  icon?: ReactNode;
  error?: string;
}

export function TextField({ label, icon, error, className = "", ...rest }: TextFieldProps) {
  return (
    <label className="block">
      <span className="mb-1.5 block text-sm font-medium text-ink-800">{label}</span>
      <div className="relative">
        {icon && (
          <span className="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-slate-400">
            {icon}
          </span>
        )}
        <input
          className={`w-full rounded-xl border bg-white py-3 text-[15px] text-ink-900 placeholder:text-slate-400 outline-none transition focus:ring-4 focus:ring-brand-500/15 ${
            icon ? "pl-11 pr-4" : "px-4"
          } ${error ? "border-red-300 focus:border-red-400" : "border-slate-200 focus:border-brand-500"} ${className}`}
          {...rest}
        />
      </div>
      {error && <span className="mt-1 block text-xs font-medium text-red-500">{error}</span>}
    </label>
  );
}
