import { createApp, h } from 'vue';
import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import Vueform from '@vueform/vueform';
import vueformConfig from './vueform.config.js';
import { i18nVue, loadLanguageAsync, getActiveLanguage } from 'laravel-vue-i18n';


import './bootstrap';

const appName = window.document.getElementsByTagName('title')[0]?.innerText || 'Laravel';

const syncAxiosCsrfToken = (page) => {
    const token = page?.props?.csrf_token;

    if (token) {
        axios.defaults.headers.common['X-CSRF-TOKEN'] = token;
    }
};

function resolvePage(name) {
    const [page, module] = name.split('::');

    const pagePath = module
        ? `../../Modules/${module}/Resources/assets/js/Pages/${page}.vue`
        : `./Pages/${page}.vue`;

    const pages = module
        ? import.meta.glob('../../Modules/**/Resources/assets/js/Pages/**/*.vue')
        : import.meta.glob(['./Pages/**/*.vue', '!./Pages/components/**/*.vue']);

    if (!pages[pagePath]) {
        const errorMessage = `Page not found: ${pagePath}`;
        console.log(errorMessage);
        throw new Error(errorMessage);
    }

    return typeof pages[pagePath] === 'function' ? pages[pagePath]() : pages[pagePath];
}

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: async (name) => await resolvePage(name),
    setup({ el, App, props, plugin }) {
      syncAxiosCsrfToken(props.initialPage);
      router.on('navigate', (event) => {
        syncAxiosCsrfToken(event.detail.page);

        // Locale is a per-domain setting (see SetApplicationLocale), so this
        // only actually reloads a language bundle when it changes -- e.g.
        // an admin with access to multiple domains switching between them.
        const nextLocale = event.detail.page?.props?.locale;
        if (nextLocale && nextLocale !== getActiveLanguage()) {
            loadLanguageAsync(nextLocale);
        }
      });

      const vueApp = createApp({ render: () => h(App, props) });

      vueApp.use(plugin);
      vueApp.use(Vueform, vueformConfig); // ✅ register Vueform IMMEDIATELY here
      vueApp.use(i18nVue, {
        lang: props.initialPage.props.locale,
        resolve: async (lang) => {
            const langs = import.meta.glob('../lang/*.json');
            return await langs[`../lang/${lang}.json`]();
        },
      });

      // MOUNT FIRST (no await for CSRF token)
      vueApp.mount(el);
  
      // THEN after mounting do CSRF, etc
      axios.defaults.withCredentials = true;
      axios.get('/sanctum/csrf-cookie');
    }
  });
