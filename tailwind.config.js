/** @type {import('tailwindcss').Config} */
export default {
  content: ["./index.html", "./src/**/*.{ts,tsx}"],
  // No class prefix needed: the iframe build is isolated by the browsing
  // context, and the WordPress embed build renders into a Shadow DOM
  // (see src/embed.tsx), so utility classes never touch the host page.
  theme: {
    extend: {
      colors: {
        brand: {
          50: "#f0fdf6",
          100: "#dbfceb",
          200: "#b9f6d5",
          300: "#82ecb4",
          400: "#46d98c",
          500: "#1fbf6c",
          600: "#149a57",
          700: "#127a48",
          800: "#12613c",
          900: "#0f4f33",
          950: "#062c1c",
        },
        ink: {
          900: "#0b1220",
          800: "#141c2e",
          700: "#1e293b",
        },
      },
      fontFamily: {
        sans: [
          "'Plus Jakarta Sans'",
          "ui-sans-serif",
          "system-ui",
          "-apple-system",
          "Segoe UI",
          "Roboto",
          "sans-serif",
        ],
      },
      boxShadow: {
        card: "0 1px 2px rgba(16,24,40,0.06), 0 1px 3px rgba(16,24,40,0.10)",
        "card-lg": "0 4px 6px -2px rgba(16,24,40,0.05), 0 12px 24px -6px rgba(16,24,40,0.14)",
        glow: "0 0 0 4px rgba(31,191,108,0.15)",
      },
      keyframes: {
        "pulse-soft": {
          "0%, 100%": { opacity: "1" },
          "50%": { opacity: "0.6" },
        },
      },
      animation: {
        "pulse-soft": "pulse-soft 1.6s ease-in-out infinite",
      },
    },
  },
  plugins: [],
};
