import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import fs from 'fs';
import tailwindcss from "@tailwindcss/vite";

const { VITE_LOG_BUILD_WARNINGS } = process.env;

export default defineConfig({
    plugins: [
        tailwindcss(),
        vue({
            template: {
                compilerOptions: {
                    isCustomElement: tag => tag.startsWith('swiper-')
                }
            }
        }),
        laravel({
            input: [
                'resources/js/spa/apps/desktop/bootstrap/application.js',
                'resources/js/spa/apps/mobile/bootstrap/application.js',

                // Business CSS/JS files
                'resources/css/business/main.css',
                'resources/js/business/main.js',


                // Desktop CSS files
                'resources/css/spa/apps/desktop/main.css',
                'resources/css/spa/apps/desktop/auth.css',

                'resources/css/spa/apps/mobile/main.css',

                'resources/fonts/stylesheet.css',

                // Document CSS
                'resources/css/document/main.css',
                'resources/js/document/main.js',

                'resources/css/admin/main.css',
                'resources/js/admin/main.js',

                'resources/js/mpa/apexcharts.js',
                'resources/js/mpa/rich.editor.js',
                'resources/css/mpa/rich.editor.css'
            ],
            refresh: true
        }),
        {
            name: 'build-number',
            buildStart() {
                // Generate a random build number and save it to the storage/frontend/build.num file
                // This is used to prevent caching of the build none packed with vite like dark theme css file.

                fs.writeFileSync('./storage/frontend/build.num', Math.floor(Math.random() * 1000000).toString());
            }
        }
    ],
    resolve: {
        alias: {
            '@': '/resources/js/spa',
            '@D': '/resources/js/spa/apps/desktop',
            '@M': '/resources/js/spa/apps/mobile',
        }
    },
    server: {
        port: 5173,
        strictPort: true,
        host: '0.0.0.0',
        hmr: {
            host: 'localhost',
            port: 5173,
            overlay: false,
        }
    },
    esbuild: {
        supported: {
            'top-level-await': true //browsers can handle top-level-await features
        }
    },
    build: {
        rollupOptions: {
            onwarn: function(warning, warn) {
                if (VITE_LOG_BUILD_WARNINGS) {
                    const today = new Date().toISOString().slice(0, 10);
                    const logFile = `./node/npm/build-logs/${today}.log`;

                    fs.appendFileSync(logFile, warning.message);
                };
            }
        }
    }
});
