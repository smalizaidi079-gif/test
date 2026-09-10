import type { ButtonHTMLAttributes, ReactNode } from "react";
import { motion } from "framer-motion";

interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: "primary" | "secondary" | "ghost";
  icon?: ReactNode;
  iconPosition?: "left" | "right";
  fullWidth?: boolean;
}

const VARIANT_CLASSES: Record<NonNullable<ButtonProps["variant"]>, string> = {
  primary:
    "bg-brand-500 text-white shadow-card-lg hover:bg-brand-600 disabled:bg-slate-200 disabled:text-slate-400 disabled:shadow-none",
  secondary:
    "bg-white text-ink-800 border border-slate-200 hover:border-slate-300 hover:bg-slate-50 disabled:text-slate-300",
  ghost: "bg-transparent text-slate-500 hover:text-ink-800 disabled:text-slate-300",
};

export function Button({
  variant = "primary",
  icon,
  iconPosition = "right",
  fullWidth,
  className = "",
  children,
  disabled,
  ...rest
}: ButtonProps) {
  return (
    <motion.button
      whileTap={disabled ? undefined : { scale: 0.97 }}
      disabled={disabled}
      className={`inline-flex items-center justify-center gap-2 rounded-2xl px-6 py-3.5 text-[15px] font-semibold transition-colors duration-150 ${
        VARIANT_CLASSES[variant]
      } ${fullWidth ? "w-full" : ""} ${disabled ? "cursor-not-allowed" : "cursor-pointer"} ${className}`}
      {...(rest as any)}
    >
      {icon && iconPosition === "left" && icon}
      {children}
      {icon && iconPosition === "right" && icon}
    </motion.button>
  );
}
