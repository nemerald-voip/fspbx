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
    theme: {
        fontFamily: {
            nunito: ["Nunito", "sans-serif"],
          },
        extend: {
            display: ['group-hover'],
            maxWidth: {'95':'95%'},
        },


    },
    plugins: [
        require('@tailwindcss/forms'),
        require('@vueform/vueform/tailwind'),
    ],
    vfDarkMode: false,
}

