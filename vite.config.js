import laravel from 'laravel-vite-plugin';
import { defineConfig } from 'vite';
import path from 'path';
import vue from '@vitejs/plugin-vue';
import basicSsl from '@vitejs/plugin-basic-ssl';
import collectModuleAssetsPaths from './vite-module-loader.js';

async function getConfig() {
    const paths = [

        "resources/js/app.js",
        "resources/js/vue.js",

        // css
        'resources/scss/tailwind.css',
        "node_modules/primevue/resources/themes/bootstrap4-light-blue/theme.css",
        "resources/scss/app-modern.scss",
        "resources/scss/icons.scss",
        "node_modules/daterangepicker/daterangepicker.css",
        // "node_modules/admin-resources/jquery.vectormap/jquery-jvectormap-1.2.2.css",
        "node_modules/bootstrap-datepicker/dist/css/bootstrap-datepicker.min.css",
        // "node_modules/fullcalendar/main.min.css",
        "node_modules/select2/dist/css/select2.min.css",
        // "node_modules/datatables.net-bs5/css/dataTables.bootstrap5.min.css",
        // "node_modules/datatables.net-responsive-bs5/css/responsive.bootstrap5.min.css",
        // "node_modules/simplemde/dist/simplemde.min.css",
        // "node_modules/frappe-gantt/dist/frappe-gantt.min.css",
        // "node_modules/quill/dist/quill.core.css",
        // "node_modules/quill/dist/quill.bubble.css",
        // "node_modules/quill/dist/quill.snow.css",
        "node_modules/jquery-toast-plugin/dist/jquery.toast.min.css",
        // "node_modules/jstree/dist/themes/default/style.min.css",
        "node_modules/britecharts/dist/css/britecharts.min.css",

        // "node_modules/flatpickr/dist/flatpickr.min.css",
        "node_modules/bootstrap-timepicker/css/bootstrap-timepicker.min.css",
        // "node_modules/bootstrap-touchspin/dist/jquery.bootstrap-touchspin.css",
        // "node_modules/datatables.net-fixedcolumns-bs5/css/fixedColumns.bootstrap5.min.css",
        // "node_modules/datatables.net-fixedheader-bs5/css/fixedHeader.bootstrap5.min.css",
        // "node_modules/datatables.net-buttons-bs5/css/buttons.bootstrap5.min.css",
        // "node_modules/datatables.net-select-bs5/css/select.bootstrap5.min.css",


        // js
        "resources/js/hyper-head.js",
        "resources/js/hyper-config.js",
        "resources/js/hyper-main.js",
        // "resources/js/hyper-layout.js",
        "resources/js/hyper-syntax.js",
        // "resources/js/ui/component.todo.js",
        "resources/js/ui/component.fileupload.js",
        "resources/js/ui/component.dragula.js",
        "resources/js/ui/component.chat.js",
        "resources/js/ui/component.toastr.js",
        "node_modules/daterangepicker/daterangepicker.js",
        // "resources/js/ui/component.range-slider.js",
        // "resources/js/ui/component.rating.js",

        // specific pages
        "resources/js/ui/page.devices.js",
    ];

    const allPaths = await collectModuleAssetsPaths(paths, 'Modules');

    return defineConfig({
        server: {
            host: '45.33.51.29',
            public: 'freeswitchpbx.us.nemerald.net',
            port: 3000
        },
        plugins: [
            basicSsl(),
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
            emptyOutDir: true
        },
        resolve: {
            alias: {
                '~bootstrap': path.resolve(__dirname, 'node_modules/bootstrap'),
                '@modules' : path.resolve(__dirname + '/modules'),
            }
        }
    });
}

export default getConfig();

// export defineConfig({
//     plugins: [
//         laravel({
//             hotFile: 'storage/vite.hot', // Customize the "hot" file...
//             buildDirectory: 'storage/vite', // Customize the build directory...
//             input: [

//                 "resources/js/app.js",
//                 "resources/js/vue.js",

//                 // css
//                 "resources/scss/app-modern.scss",
//                 "resources/scss/icons.scss",
//                 "node_modules/daterangepicker/daterangepicker.css",
//                 // "node_modules/admin-resources/jquery.vectormap/jquery-jvectormap-1.2.2.css",
//                 "node_modules/bootstrap-datepicker/dist/css/bootstrap-datepicker.min.css",
//                 // "node_modules/fullcalendar/main.min.css",
//                 "node_modules/select2/dist/css/select2.min.css",
//                 // "node_modules/datatables.net-bs5/css/dataTables.bootstrap5.min.css",
//                 // "node_modules/datatables.net-responsive-bs5/css/responsive.bootstrap5.min.css",
//                 // "node_modules/simplemde/dist/simplemde.min.css",
//                 // "node_modules/frappe-gantt/dist/frappe-gantt.min.css",
//                 // "node_modules/quill/dist/quill.core.css",
//                 // "node_modules/quill/dist/quill.bubble.css",
//                 // "node_modules/quill/dist/quill.snow.css",
//                 "node_modules/jquery-toast-plugin/dist/jquery.toast.min.css",
//                 // "node_modules/jstree/dist/themes/default/style.min.css",
//                 "node_modules/britecharts/dist/css/britecharts.min.css",

//                 // "node_modules/flatpickr/dist/flatpickr.min.css",
//                 "node_modules/bootstrap-timepicker/css/bootstrap-timepicker.min.css",
//                 // "node_modules/bootstrap-touchspin/dist/jquery.bootstrap-touchspin.css",
//                 // "node_modules/datatables.net-fixedcolumns-bs5/css/fixedColumns.bootstrap5.min.css",
//                 // "node_modules/datatables.net-fixedheader-bs5/css/fixedHeader.bootstrap5.min.css",
//                 // "node_modules/datatables.net-buttons-bs5/css/buttons.bootstrap5.min.css",
//                 // "node_modules/datatables.net-select-bs5/css/select.bootstrap5.min.css",


//                 // js
//                 "resources/js/hyper-head.js",
//                 "resources/js/hyper-config.js",
//                 "resources/js/hyper-main.js",
//                 // "resources/js/hyper-layout.js",
//                 "resources/js/hyper-syntax.js",
//                 // "resources/js/ui/component.todo.js",
//                 "resources/js/ui/component.fileupload.js",
//                 "resources/js/ui/component.dragula.js",
//                 "resources/js/ui/component.chat.js",
//                 "resources/js/ui/component.toastr.js",
//                 // "resources/js/ui/component.range-slider.js",
//                 // "resources/js/ui/component.rating.js",
//             ],
//             refresh: true,
//         }),
//         vue({
//             template: {
//                 transformAssetUrls: {
//                     // The Vue plugin will re-write asset URLs, when referenced
//                     // in Single File Components, to point to the Laravel web
//                     // server. Setting this to `null` allows the Laravel plugin
//                     // to instead re-write asset URLs to point to the Vite
//                     // server instead.
//                     base: null,

//                     // The Vue plugin will parse absolute URLs and treat them
//                     // as absolute paths to files on disk. Setting this to
//                     // `false` will leave absolute URLs un-touched so they can
//                     // reference assets in the public directory as expected.
//                     includeAbsolute: false,
//                 },
//             },
//         }),
//     ],
//     build: {
//         outDir: 'storage/app/public/vite',
//         emptyOutDir: true
//     },
//     resolve: {
//         alias: {
//             '~bootstrap': path.resolve(__dirname, 'node_modules/bootstrap')
//         }
//     }
// });
