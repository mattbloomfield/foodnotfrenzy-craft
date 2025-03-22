import {defineConfig} from 'vite';
import path from 'path';
import viteCompression from 'vite-plugin-compression';
import tailwindcss from "@tailwindcss/vite";

// https://vitejs.dev/config/
export default defineConfig(({command}) => ({
    base: command === 'serve' ? '' : '/dist/',

    build: {
        manifest: true,
        emptyOutDir: true,
        outDir: 'web/dist/',
        rollupOptions: {
            input: {
                app: 'src/js/app.js',
            },
        },
    },

    publicDir: 'public',

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
    ],
}));
