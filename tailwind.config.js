import defaultTheme from "tailwindcss/defaultTheme";

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        "./vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php",
        "./storage/framework/views/*.php",
        "./resources/**/*.blade.php",
        "./resources/**/*.js",
        "./resources/**/*.vue",
    ],
    // theme: {
    //     extend: {
    //         fontFamily: {
    //             sans: ["Figtree", ...defaultTheme.fontFamily.sans],
    //         },
    //     },
    // },
    theme: {
        extend: {
            borderRadius: { xl: "0.75rem", "2xl": "1rem" },
            boxShadow: {
                card: "0 1px 2px rgba(16,24,40,.06), 0 1px 3px rgba(16,24,40,.1)",
                hover: "0 8px 24px rgba(16,24,40,.12)",
            },
            colors: {
                brand: {
                    DEFAULT: "#2563EB",
                    50: "#eff6ff",
                    600: "#2563EB",
                    700: "#1D4ED8",
                },
            },
        },
    },
    plugins: [],
};
