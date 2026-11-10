import {defineConfig} from 'vite';
import fs from 'node:fs';
import viteCompression from 'vite-plugin-compression';
import tailwindcss from "@tailwindcss/vite";

// Craft 6 templates load assets with Laravel's `vite()` Twig function, which
// reads public/dist/manifest.json in production. While `vite` (dev server) is
// running, it writes public/hot so `vite()` points at the dev server instead.
const hotFile = 'public/hot';
const hotFilePlugin = {
    name: 'laravel-hot-file',
    apply: 'serve',
    configureServer(server) {
        // DDEV exposes the dev server on https port 5174 (see .ddev/config.yaml).
        const url = process.env.DDEV_PRIMARY_URL
            ? `${process.env.DDEV_PRIMARY_URL}:5174`
            : 'http://localhost:5173';
        fs.writeFileSync(hotFile, url);
        const clean = () => fs.existsSync(hotFile) && fs.rmSync(hotFile);
        server.httpServer?.once('close', clean);
        ['SIGINT', 'SIGTERM', 'SIGHUP'].forEach((signal) => process.once(signal, () => {
            clean();
            process.exit();
        }));
    },
};

// https://vitejs.dev/config/
export default defineConfig(({command}) => ({
    base: command === 'serve' ? '' : '/dist/',

    build: {
        manifest: 'manifest.json',
        emptyOutDir: true,
        outDir: 'public/dist/',
        rollupOptions: {
            input: {
                app: 'src/js/app.js',
            },
        },
    },

    publicDir: 'src/public',

    server: {
        allowedHosts: true,
        cors: true,
        host: '0.0.0.0',
        port: 5173, // Use port 5173 for dev server.
        strictPort: true, // Don't try next available port if 5173 isn't available.
        origin: 'http://localhost:5173', // Rewrite asset URLs explicitly since the CMS web server is on a different host.
    },

    plugins: [
        tailwindcss(),
        viteCompression({
            filter: /\.(js|mjs|json|css|map)$/i
        }),
        hotFilePlugin,
    ],
}));
