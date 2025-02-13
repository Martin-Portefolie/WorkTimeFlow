/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    './assets/**/*.{html,js,twig}',
    './templates/**/*.twig',
  ],
  theme: {
    extend: {
      colors: {
        primary: "#7C3AED",
        secondary: "#A78BFA",
        background: "#F5EFFF",
        neutral: {
          100: "#FAF5FF",
          500: "#6B7280",
          900: "#1F2937",
        },
        success: "#10B981", // Green (active)
        warning: "#F59E0B", // Yellow (pending)
        error: "#EF4444", // Red (errors)
      },
    fontFamily:{
        sans: ["inter", "sans-serif"],
        display: ["Poppins", "sans-serif"],
        logo: ["Pacifico", "cursive"]
    },
      fontsize:{
        sm: "0.875rem",
        base: "1rem",
        lg: "1.125rem",
        xl: "1.25rem",
        "2xl": "1.5rem",
        "3xl": "2rem",
        "4xl": "2.5rem",
      }
    },
  },
  plugins: [],
};
