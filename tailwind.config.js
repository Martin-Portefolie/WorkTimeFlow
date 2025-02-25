/** @type {import('tailwindcss').Config} */

const grey = {
  0: "#FFFFFF",  // Pure white
  50: "#FCFCFC",  // Very light grey
  100: "#F2F2F2", // Soft grey
  150: "#E5E5E5", // Light grey
  200: "#D6D6D6", // Medium-light grey
  300: "#BFBFBF", // Neutral grey
  400: "#999999", // Medium grey
  500: "#707070", // Darker medium grey
  700: "#4A4A4A", // Dark grey
  900: "#1F1F1F", // Almost black
};

const custom_colors = {
  dark_purple: "#7C3AED",
      purple: "#A78BFA",
      blue: "#2563EB",
      blue_hoover: "#4F8FF7",
      pastel_rosa: "#F5EFFF",
      pastel_green: "#EFFFF5"
};

module.exports = {
  content: [
    './assets/**/*.{html,js,twig}',
    './templates/**/*.twig',
  ],
  theme: {
    extend: {
      colors: {
        primary: grey[500],
        secondary: grey[400],
        alternate: "red",
        background: grey[0],
        section_1: custom_colors.pastel_green,
        section_2: custom_colors.pastel_rosa,

        blue: custom_colors.blue,

        grey,
        custom_colors,

        neutral: {
          100: "#FAF5FF",
          500: "#6B7280",
          900: "#1F2937",
        },

        white: grey[0],  // ✅ White
        dark: grey[900], // ✅ Darkest Grey

        success: "#10B981", // Green (active)
        warning: "#F59E0B", // Yellow (pending)
        error: "#EF4444", // Red (errors)
      },

      fontFamily: {
        sans: ["Inter", "sans-serif"],
        display: ["Poppins", "sans-serif"],
        cursive: ["Pacifico", "cursive"],
      },

      fontSize: {
        sm: "0.875rem",
        base: "1rem",
        lg: "1.125rem",
        xl: "1.25rem",
        "2xl": "1.5rem",
        "3xl": "2rem",
        "4xl": "2.5rem",
      },
    },
  },
  plugins: [],
};

