/** @type {import('tailwindcss').Config} */
module.exports = {
    content: [
        "./templates/**/*.html.twig",
        "./templates/**/*.twig",
        "./assets/**/*.{js,ts,css,twig,html}",
    ],
    darkMode: 'class', // we’ll toggle by adding/removing .dark on <html> or <body>
    theme: {
        extend: {
            colors: {
                // Semantic system colors -> CSS vars
                background: "var(--background)",
                foreground: "var(--foreground)",
                card: "var(--card-background)",
                "card-foreground": "var(--card-foreground)",
                popover: "var(--popover)",
                "popover-foreground": "var(--popover-foreground)",
                primary: "var(--primary)",
                "primary-foreground": "var(--primary-foreground)",
                secondary: "var(--secondary)",
                "secondary-foreground": "var(--secondary-foreground)",
                muted: "var(--muted)",
                "muted-foreground": "var(--muted-foreground)",
                accent: "var(--accent)",
                "accent-foreground": "var(--accent-foreground)",
                destructive: "var(--destructive)",
                "destructive-foreground": "var(--destructive-foreground)",
                border: "var(--border)",
                input: "var(--input)",
                "input-background": "var(--input-background)",
                "switch-background": "var(--switch-background)",
                ring: "var(--ring)",

                // Brand & terminal
                "brand-emerald": "var(--brand-emerald)",
                "brand-teal": "var(--brand-teal)",
                "accent-mint": "var(--accent-mint)",
                "terminal-bg": "var(--terminal-bg)",
                "terminal-text": "var(--terminal-text)",
                "terminal-accent": "var(--terminal-accent)",

                // Project border states
                "project-border-active": "var(--project-border-active)",
                "project-border-paid": "var(--project-border-paid)",
                "project-border-archived": "var(--project-border-archived)",
                "project-border-paid-archived": "var(--project-border-paid-archived)",

                // Status
                success: "var(--success)",
                danger: "var(--danger)",
                warning: "var(--warning)",
                info: "var(--info)",
            },
            borderRadius: {
                sm: "calc(var(--radius) - 4px)",
                md: "calc(var(--radius) - 2px)",
                lg: "var(--radius)",
                xl: "calc(var(--radius) + 4px)",
            },
            boxShadow: {
                card: "0 8px 24px rgba(0,0,0,0.08)",
                pop: "0 12px 40px rgba(0,0,0,0.15)",
            },
            fontFamily: {
                sans: ["Inter", "ui-sans-serif", "system-ui", "sans-serif"],
                display: ["Poppins", "ui-sans-serif", "system-ui", "sans-serif"],
            },
        },
    },
    plugins: [],
};
