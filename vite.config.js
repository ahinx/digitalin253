// import { defineConfig, loadEnv } from "vite";
// import laravel from "laravel-vite-plugin";

// export default defineConfig(({ command, mode }) => {
//     return {
//         plugins: [
//             laravel({
//                 input: ["resources/css/app.css", "resources/js/app.js"],
//                 refresh: true,
//             }),
//         ],
//         server: {
//             hmr: {
//                 host: "yak-magical-lizard.ngrok-free.app",
//                 protocol: "https",
//             },
//         },
//     };
// });

import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";

export default defineConfig({
    plugins: [
        laravel({
            input: ["resources/css/app.css", "resources/js/app.js"],
            refresh: true,
        }),
    ],
    server: {
        host: true,
        port: 5173, // Vite listen lokal
        hmr: {
            host:
                process.env.VITE_DEV_SERVER_HOST ||
                "abnormally-next-chicken.ngrok-free.app",
            protocol: "wss", // HMR lewat HTTPS
            clientPort: 443, // wajib agar browser pakai 443, bukan :5173
        },
    },
});
