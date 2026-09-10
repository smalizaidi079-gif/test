import { Button } from "@/components/ui/Button";
import { ArrowRight, ChevronLeft } from "@/components/ui/icons";

interface NavigationButtonsProps {
  onBack?: () => void;
  onNext: () => void;
  nextLabel?: string;
  disabled?: boolean;
  showBack?: boolean;
}

export function NavigationButtons({
  onBack,
  onNext,
  nextLabel = "Continue",
  disabled,
  showBack = true,
}: NavigationButtonsProps) {
  return (
    <div className="mt-8 flex items-center gap-3">
      {showBack && onBack && (
        <Button variant="ghost" onClick={onBack} icon={<ChevronLeft className="h-4 w-4" />} iconPosition="left">
          Back
        </Button>
      )}
      <Button
        variant="primary"
        onClick={onNext}
        disabled={disabled}
        fullWidth
        icon={<ArrowRight className="h-4 w-4" />}
      >
        {nextLabel}
      </Button>
    </div>
  );
}
