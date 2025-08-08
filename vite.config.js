import { defineConfig, loadEnv } from "vite";
import laravel from "laravel-vite-plugin";

export default defineConfig(({ command, mode }) => {
    return {
        plugins: [
            laravel({
                input: ["resources/css/app.css", "resources/js/app.js"],
                refresh: true,
            }),
        ],
        server: {
            hmr: {
                host: "yak-magical-lizard.ngrok-free.app",
                protocol: "https",
            },
        },
    };
});
