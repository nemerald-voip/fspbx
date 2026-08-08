import { createApp, h } from 'vue';
import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import Vueform from '@vueform/vueform';
import vueformConfig from './vueform.config.js';
import { i18nVue, loadLanguageAsync, getActiveLanguage } from '@i18n';


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

// Base-first chain of locale codes to merge for a given locale, e.g.
// { 'es-mx': ['en-us', 'es-es', 'es-419', 'es-mx'] } -- keyed by every
// locale we've actually seen in an Inertia page's props, since the active
// domain (and so the active locale) can change without a full page reload.
// Seeded from the initial page load; kept up to date in the 'navigate'
// handler below, matching what App\Support\Localization\LocaleRegistry::
// chain() computes server-side for the same locale.
const localeChains = {};

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: async (name) => await resolvePage(name),
    setup({ el, App, props, plugin }) {
      syncAxiosCsrfToken(props.initialPage);

      const initialLocale = props.initialPage.props.locale;
      localeChains[initialLocale] = props.initialPage.props.localeChain ?? [initialLocale];

      router.on('navigate', (event) => {
        syncAxiosCsrfToken(event.detail.page);

        // Locale is a per-domain setting (see SetApplicationLocale), so this
        // only actually reloads a language bundle when it changes -- e.g.
        // an admin with access to multiple domains switching between them.
        const nextLocale = event.detail.page?.props?.locale;
        if (nextLocale && nextLocale !== getActiveLanguage()) {
            localeChains[nextLocale] = event.detail.page?.props?.localeChain ?? [nextLocale];
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
            const chain = localeChains[lang] ?? [lang];

            // Merge base-first (en-us, ..., lang) so a more specific dialect
            // overrides its parent's translation, mirroring the backend's
            // LocaleFileLoader merge. An empty string means "not translated
            // yet" (lang:sync seeds every locale file with these so
            // translators can see the full key list), not a real
            // translation -- drop it at each link so it defers to whatever
            // the next link up the chain has, instead of rendering blank
            // text or shadowing a parent's real translation.
            let merged = {};
            for (const link of chain) {
                const mod = await langs[`../lang/${link}.json`]?.();

                if (!mod) {
                    continue;
                }

                for (const [key, value] of Object.entries(mod.default)) {
                    if (value !== '') {
                        merged[key] = value;
                    }
                }
            }

            return { default: merged };
        },
      });

      // MOUNT FIRST (no await for CSRF token)
      vueApp.mount(el);

      // THEN after mounting do CSRF, etc
      axios.defaults.withCredentials = true;
      axios.get('/sanctum/csrf-cookie');
    }
  });
