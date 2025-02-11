/** @type {import('tailwindcss').Config} */
module.exports = {
  theme: {
    extend: {
      colors: {
        primary: "#7C3AED", // Deep purple for accents
        secondary: "#A78BFA", // Soft lavender for highlights
        background: "#F5EFFF", // Light purple background
        neutral: {
          100: "#FAF5FF", // Lighter purple-gray for cards
          500: "#6B7280", // Standard text gray
          900: "#1F2937", // Dark gray for strong contrast
        },
        success: "#10B981", // Green (active)
        warning: "#F59E0B", // Yellow (pending)
        error: "#EF4444", // Red (errors)
      },
    },
  },
};

