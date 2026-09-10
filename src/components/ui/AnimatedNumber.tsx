import { useEffect, useRef, useState } from "react";
import { animate } from "framer-motion";

interface AnimatedNumberProps {
  value: number;
  formatter?: (n: number) => string;
  className?: string;
}

export function AnimatedNumber({ value, formatter, className }: AnimatedNumberProps) {
  const [display, setDisplay] = useState(value);
  const previousRef = useRef(value);

  useEffect(() => {
    const controls = animate(previousRef.current, value, {
      duration: 0.5,
      ease: "easeOut",
      onUpdate: (v) => setDisplay(v),
    });
    previousRef.current = value;
    return () => controls.stop();
  }, [value]);

  return <span className={className}>{formatter ? formatter(display) : Math.round(display)}</span>;
}
