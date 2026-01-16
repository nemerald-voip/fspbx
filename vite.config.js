import laravel from 'laravel-vite-plugin';
import { defineConfig } from 'vite';
import path from 'path';
import vue from '@vitejs/plugin-vue';
import collectModuleAssetsPaths from './vite-module-loader.js';
import fs from 'fs';

const VITE_HOST = process.env.VITE_HOST || 'localhost';
const VITE_PORT = process.env.VITE_PORT || 3000;
const VITE_ORIGIN = process.env.VITE_ORIGIN;

async function getConfig() {
    console.log('Vite configured host:', VITE_HOST);
    console.log('Vite configured port:', VITE_PORT);
    console.log('Vite configured CORS origin:', VITE_ORIGIN);

    const paths = [

        "resources/js/app.js",
        "resources/js/vue.js",

        // css
        'resources/scss/tailwind.css',
        "resources/scss/app-modern.scss",
        "resources/scss/icons.scss",
        "node_modules/daterangepicker/daterangepicker.css",
        "node_modules/bootstrap-datepicker/dist/css/bootstrap-datepicker.min.css",
        "node_modules/select2/dist/css/select2.min.css",
        "node_modules/jquery-toast-plugin/dist/jquery.toast.min.css",
        "node_modules/britecharts/dist/css/britecharts.min.css",
        "node_modules/bootstrap-timepicker/css/bootstrap-timepicker.min.css",

        // js
        "resources/js/hyper-head.js",
        "resources/js/hyper-config.js",
        "resources/js/hyper-main.js",
        // "resources/js/hyper-layout.js",
        "resources/js/hyper-syntax.js",
        // "resources/js/ui/component.todo.js",
        // "resources/js/ui/component.fileupload.js",
        // "resources/js/ui/component.dragula.js",
        // "resources/js/ui/component.chat.js",
        "resources/js/ui/component.toastr.js",
        "node_modules/daterangepicker/daterangepicker.js",
        // "resources/js/ui/component.range-slider.js",
        // "resources/js/ui/component.rating.js",
    ];

    const allPaths = await collectModuleAssetsPaths(paths, 'Modules');


    const keyPath = '/etc/nginx/ssl/private/privkey.pem'
    const certPath = '/etc/nginx/ssl/fullchain.pem'

    let httpsConfig = null

    if (fs.existsSync(keyPath) && fs.existsSync(certPath)) {
        httpsConfig = {
            key: fs.readFileSync(keyPath),
            cert: fs.readFileSync(certPath),
        }
    } else {
        httpsConfig = false    // Or use null, but Vite expects false for HTTP fallback
    }

    return defineConfig({
        server: {
            host: VITE_HOST,
            port: VITE_PORT,
            https: httpsConfig,
            watch: {
                usePolling: true,
                interval: 1000,
            },
            cors: {
                origin: VITE_ORIGIN,
            },

        },
        plugins: [
            laravel({
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
                },
            }),
        ],
        build: {
            outDir: 'storage/app/public/vite',
            emptyOutDir: true,
            sourcemap: false,
            reportCompressedSize: false,
            minify: 'esbuild',
            cssMinify: 'esbuild',
        },
        resolve: {
            alias: {
                '~bootstrap': path.resolve(__dirname, 'node_modules/bootstrap'),
                '@modules': path.resolve(__dirname + '/modules'),
                '@layouts': path.resolve(__dirname, 'resources/js/Layouts'),
                '@icons': path.resolve(__dirname, 'resources/js/Pages/components/icons'),
                '@generalComponents': path.resolve(__dirname, 'resources/js/Pages/components/general'),
            }
        }
    });
}

export default getConfig();

