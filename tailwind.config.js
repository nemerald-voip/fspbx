/** @type {import('tailwindcss').Config} */
export default {
    content: [
        "./resources/**/*.blade.php",
        "./resources/**/*.js",
        "./resources/**/*.vue",
        "./Modules/**/Resources/assets/js/**/*.vue", // Include module Vue files
        './resources/js/vueform.config.js', // or where `vueform.config.js` is located. Change `.js` to `.ts` if required.
        './node_modules/@vueform/vueform/themes/tailwind/**/*.vue',
        './node_modules/@vueform/vueform/themes/tailwind/**/*.js',
    ],
    // prefix: 'tw-',
    // corePlugins: {
    //     preflight: false,
    //   },
    darkMode: 'class',
    theme: {
        fontFamily: {
            nunito: ["Nunito", "sans-serif"],
          },
        extend: {
            display: ['group-hover'],
            maxWidth: {'95':'95%'},
            // Semantic color tokens — backed by CSS variables defined in
            // resources/scss/tailwind.css. See documentation/dark-mode-palette.md.
            // Vars hold RGB channels, wrapped here in rgb(var / <alpha-value>) so
            // opacity modifiers work (bg-danger/15, ring-success/20, bg-surface/50).
            // The default Tailwind palette (gray/indigo/...) is preserved via
            // `extend`, so existing hardcoded utilities keep working during migration.
            colors: (() => {
                const c = (v) => `rgb(var(${v}) / <alpha-value>)`;
                return {
                    bg: c('--color-bg'),
                    surface: {
                        DEFAULT: c('--color-surface'),
                        2: c('--color-surface-2'),
                        3: c('--color-surface-3'),
                    },
                    overlay: 'var(--color-overlay)', // baked-in alpha

                    // text roles (text-heading / text-body / text-muted / text-subtle)
                    heading: c('--color-text-heading'),
                    body: c('--color-text-body'),
                    muted: c('--color-text-muted'),
                    subtle: c('--color-text-subtle'),
                    'on-accent': c('--color-on-accent'),

                    // borders / focus (border-default / border-strong / ring-focus)
                    default: c('--color-border-default'),
                    strong: c('--color-border-strong'),
                    focus: c('--color-focus'),

                    // accent (bg-accent / hover:bg-accent-hover / text-accent-fg)
                    accent: {
                        DEFAULT: c('--color-accent'),
                        hover: c('--color-accent-hover'),
                        fg: c('--color-accent-fg'),
                        subtle: 'rgb(var(--color-accent) / 0.12)',
                    },

                    // status (text-success / bg-success-subtle, etc.)
                    // -subtle = base color at 15% alpha (works in both themes).
                    // -solid / -solid-hover = opaque fills for buttons with white
                    // text; they stay dark in dark mode so contrast holds.
                    success: { DEFAULT: c('--color-success'), subtle: 'rgb(var(--color-success) / 0.15)', solid: c('--color-success-solid'), 'solid-hover': c('--color-success-solid-hover') },
                    danger: { DEFAULT: c('--color-danger'), subtle: 'rgb(var(--color-danger) / 0.15)', solid: c('--color-danger-solid'), 'solid-hover': c('--color-danger-solid-hover') },
                    warning: { DEFAULT: c('--color-warning'), subtle: 'rgb(var(--color-warning) / 0.15)', solid: c('--color-warning-solid'), 'solid-hover': c('--color-warning-solid-hover') },
                    info: { DEFAULT: c('--color-info'), subtle: 'rgb(var(--color-info) / 0.15)', solid: c('--color-info-solid'), 'solid-hover': c('--color-info-solid-hover') },
                };
            })(),
        },


    },
    plugins: [
        require('@tailwindcss/forms'),
        require('@vueform/vueform/tailwind'),
    ],
    vfDarkMode: false,
}

