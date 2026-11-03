import laravel from 'laravel-vite-plugin';
import { defineConfig } from 'vite';
import path from 'path';
import vue from '@vitejs/plugin-vue';
import i18n from 'laravel-vue-i18n/vite';
import { contract, moduleAssets } from './build/module-assets.mjs';
import fs from 'fs';
import deepChatImageLoadFix from './build/deep-chat-image-load-fix.mjs';

const VITE_HOST = process.env.VITE_HOST || 'localhost';
const VITE_PORT = process.env.VITE_PORT || 3000;
const VITE_ORIGIN = process.env.VITE_ORIGIN;

async function getConfig({ command }) {
    const moduleName = process.env.FSPBX_BUILD_MODULE;
    console.log('Vite configured host:', VITE_HOST);
    console.log('Vite configured port:', VITE_PORT);
    console.log('Vite configured CORS origin:', VITE_ORIGIN);

    const paths = [
        "resources/js/vue.js",

        'resources/scss/tailwind.css',
    ];

    const allPaths = moduleName ? ['virtual:fspbx-module-entry'] : [...paths, ...Object.values(contract.shared)];


    const keyPath = '/etc/nginx/ssl/private/privkey.pem'
    const certPath = '/etc/nginx/ssl/fullchain.pem'

    let httpsConfig = null

    if (command === 'serve' && fs.existsSync(keyPath) && fs.existsSync(certPath)) {
        httpsConfig = {
            key: fs.readFileSync(keyPath),
            cert: fs.readFileSync(certPath),
        }
    } else {
        httpsConfig = false    // Or use null, but Vite expects false for HTTP fallback
    }

    return defineConfig({
        base: command === 'build' ? './' : undefined,
        publicDir: false,
        server: {
            host: VITE_HOST,
            port: VITE_PORT,
            https: httpsConfig,
            watch: {
                usePolling: true,
                interval: 1000,
            },
            fs: {
                strict: true,
                deny: [
                    '.env',
                    '.env.*',
                    '*.{crt,pem}',
                    '**/.git/**',
                    '**/.DS_Store',
                ],
            },
            cors: {
                origin: VITE_ORIGIN,
            },

        },
        plugins: [
            moduleAssets({ root: process.cwd(), serve: command === 'serve', moduleName }),
            deepChatImageLoadFix(),
            !moduleName && laravel({
                hotFile: 'storage/vite.hot', // Customize the "hot" file...
                buildDirectory: 'storage/vite', // Customize the build directory...
                input: allPaths,
                refresh: true,
            }),
            vue({
                template: {
                    transformAssetUrls: {
                        // The Vue plugin will re-write asset URLs, when referenced
                        // in Single File Components, to point to the Laravel web
                        // server. Setting this to `null` allows the Laravel plugin
                        // to instead re-write asset URLs to point to the Vite
                        // server instead.
                        base: null,

                        // The Vue plugin will parse absolute URLs and treat them
                        // as absolute paths to files on disk. Setting this to
                        // `false` will leave absolute URLs un-touched so they can
                        // reference assets in the public directory as expected.
                        includeAbsolute: false,
                    },
                    compilerOptions: {
                        isCustomElement: (tag) => tag === 'deep-chat'
                    }
                },
            }),
            // Exposes app-defined resources/lang/*.php translation files (e.g.
            // Laravel's own validation.php) to Vue alongside the *.json
            // catalogs the app loads directly via i18nVue's resolve().
            i18n('resources/lang'),
        ].filter(Boolean),
        // Apply the image-load fix in development as well as production builds.
        optimizeDeps: { exclude: ['deep-chat'] },
        build: {
            outDir: 'storage/app/public/vite',
            emptyOutDir: true,
            sourcemap: false,
            reportCompressedSize: false,
            minify: 'esbuild',
            cssMinify: 'esbuild',
            chunkSizeWarningLimit: 1500,
            manifest: true,
            rollupOptions: {
                input: allPaths,
                preserveEntrySignatures: 'strict',
            },
        },
        css: {
            preprocessorOptions: {
                scss: {
                    silenceDeprecations: ['legacy-js-api'],
                },
            },
        },
        resolve: {
            alias: {
                '~bootstrap': path.resolve(__dirname, 'node_modules/bootstrap'),
                '@modules': path.resolve(__dirname + '/modules'),
                '@layouts': path.resolve(__dirname, 'resources/js/Layouts'),
                '@icons': path.resolve(__dirname, 'resources/js/Pages/components/icons'),
                '@generalComponents': path.resolve(__dirname, 'resources/js/Pages/components/general'),
                '@i18n': path.resolve(__dirname, 'resources/js/i18n.mjs'),
            }
        }
    });
}

export default defineConfig(getConfig);
