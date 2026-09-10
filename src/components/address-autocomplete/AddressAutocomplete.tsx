import { useEffect, useRef, useState } from "react";
import { AnimatePresence, motion } from "framer-motion";
import { MapPin, Loader2 } from "@/components/ui/icons";
import { addressProvider } from "./index";
import type { AddressSuggestion } from "./AddressProvider";
import type { AddressDetails } from "@/types/booking";

interface AddressAutocompleteProps {
  value: string;
  onChange: (value: string) => void;
  onSelect: (address: AddressDetails) => void;
  placeholder?: string;
}

const DEBOUNCE_MS = 200;

export function AddressAutocomplete({
  value,
  onChange,
  onSelect,
  placeholder,
}: AddressAutocompleteProps) {
  const [suggestions, setSuggestions] = useState<AddressSuggestion[]>([]);
  const [isLoading, setIsLoading] = useState(false);
  const [isOpen, setIsOpen] = useState(false);
  const containerRef = useRef<HTMLDivElement>(null);
  const debounceRef = useRef<ReturnType<typeof setTimeout>>();
  const requestIdRef = useRef(0);
  const lastSelectedValueRef = useRef<string | null>(null);

  useEffect(() => {
    function handleClickOutside(event: MouseEvent) {
      if (containerRef.current && !containerRef.current.contains(event.target as Node)) {
        setIsOpen(false);
      }
    }
    document.addEventListener("mousedown", handleClickOutside);
    return () => document.removeEventListener("mousedown", handleClickOutside);
  }, []);

  useEffect(() => {
    if (debounceRef.current) clearTimeout(debounceRef.current);

    // Skip re-searching when `value` changed because a suggestion was just
    // selected (as opposed to the user typing) — otherwise picking a
    // suggestion immediately re-opens the dropdown with a fresh search.
    if (value === lastSelectedValueRef.current) {
      return;
    }

    if (value.trim().length < 2) {
      setSuggestions([]);
      setIsLoading(false);
      return;
    }

    setIsLoading(true);
    const currentRequestId = ++requestIdRef.current;

    debounceRef.current = setTimeout(async () => {
      const results = await addressProvider.getSuggestions(value);
      if (requestIdRef.current === currentRequestId) {
        setSuggestions(results);
        setIsLoading(false);
        setIsOpen(true);
      }
    }, DEBOUNCE_MS);

    return () => {
      if (debounceRef.current) clearTimeout(debounceRef.current);
    };
  }, [value]);

  async function handleSelect(suggestion: AddressSuggestion) {
    const details = await addressProvider.resolveSuggestion(suggestion);
    requestIdRef.current++; // invalidate any in-flight search
    lastSelectedValueRef.current = details.formatted;
    setIsLoading(false);
    setIsOpen(false);
    setSuggestions([]);
    onChange(details.formatted);
    onSelect(details);
  }

  return (
    <div ref={containerRef} className="relative w-full">
      <div className="relative">
        <MapPin className="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" />
        <input
          type="text"
          value={value}
          onChange={(e) => onChange(e.target.value)}
          onFocus={() => value.trim().length >= 2 && setIsOpen(true)}
          placeholder={placeholder ?? "Start typing your address…"}
          autoComplete="off"
          className="w-full rounded-2xl border border-slate-200 bg-white py-4 pl-12 pr-11 text-[15px] text-ink-900 placeholder:text-slate-400 shadow-card outline-none transition focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15"
        />
        {isLoading && (
          <Loader2 className="absolute right-4 top-1/2 h-5 w-5 -translate-y-1/2 animate-spin text-brand-500" />
        )}
      </div>

      <AnimatePresence>
        {isOpen && suggestions.length > 0 && (
          <motion.ul
            initial={{ opacity: 0, y: -6, scale: 0.98 }}
            animate={{ opacity: 1, y: 0, scale: 1 }}
            exit={{ opacity: 0, y: -6, scale: 0.98 }}
            transition={{ duration: 0.15 }}
            className="absolute z-20 mt-2 w-full overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-card-lg"
          >
            {suggestions.map((suggestion) => (
              <li key={suggestion.id}>
                <button
                  type="button"
                  onClick={() => handleSelect(suggestion)}
                  className="flex w-full items-center gap-3 px-4 py-3 text-left text-sm text-ink-800 transition hover:bg-brand-50"
                >
                  <MapPin className="h-4 w-4 shrink-0 text-brand-500" />
                  <span>{suggestion.description}</span>
                </button>
              </li>
            ))}
          </motion.ul>
        )}
      </AnimatePresence>

      <p className="mt-2 text-xs text-slate-400">
        Demo address lookup — no external maps API is used.
      </p>
    </div>
  );
}
