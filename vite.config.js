import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";
import { viteStaticCopy } from "vite-plugin-static-copy";

export default defineConfig({
    plugins: [
        laravel({
            input: [
                "resources/css/app.css",
                "resources/js/bootstrap.js",
                "resources/js/app.js",
                "resources/js/mission-control/session.js",
            ],
            refresh: true,
        }),
        viteStaticCopy({
            targets: [
                { src: "resources/img", dest: ".." },
                { src: "node_modules/@fortawesome/fontawesome-free/webfonts", dest: ".." },
            ],
        }),
    ],

    // This affects transforms in the main pipeline, but not always deps prebundle
    esbuild: {
        target: "es2022",
    },

    optimizeDeps: {
        esbuildOptions: {
            target: "es2022",
        },
    },

    server: {
        port: 9999,
    },

    build: {
        target: "es2022",
        cssMinify: "esbuild",
        minify: "esbuild",
        chunkSizeWarningLimit: 2048,
        sourcemap: true,
    },
});
