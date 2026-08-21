import {
    currentLocale,
    getActiveLanguage,
    i18nVue as baseI18nVue,
    isLoaded,
    loadLanguageAsync,
    reset,
    trans as baseTrans,
    transChoice as baseTransChoice,
    wTrans as baseWTrans,
    wTransChoice as baseWTransChoice,
} from 'laravel-vue-i18n';

export {
    currentLocale,
    getActiveLanguage,
    isLoaded,
    loadLanguageAsync,
    reset,
};

export const normalizeTranslationReplacements = (replacements = {}) => Object.fromEntries(
    Object.entries(replacements ?? {}).map(([key, value]) => [key, value ?? ''])
);

const normalizeTranslationKey = (key) => String(key ?? '');
const normalizeChoiceNumber = (number) => number ?? 0;

export const trans = (key, replacements = {}) => baseTrans(
    normalizeTranslationKey(key),
    normalizeTranslationReplacements(replacements)
);

export const wTrans = (key, replacements = {}) => baseWTrans(
    normalizeTranslationKey(key),
    normalizeTranslationReplacements(replacements)
);

export const transChoice = (key, number, replacements = {}) => baseTransChoice(
    normalizeTranslationKey(key),
    normalizeChoiceNumber(number),
    normalizeTranslationReplacements(replacements)
);

export const wTransChoice = (key, number, replacements = {}) => baseWTransChoice(
    normalizeTranslationKey(key),
    normalizeChoiceNumber(number),
    normalizeTranslationReplacements(replacements)
);

export const trans_choice = transChoice;

export const i18nVue = {
    install(app, options = {}) {
        baseI18nVue.install(app, options);

        const baseTemplateTrans = app.config.globalProperties.$t;
        const baseTemplateTransChoice = app.config.globalProperties.$tChoice;

        app.config.globalProperties.$t = (key, replacements = {}) => baseTemplateTrans(
            normalizeTranslationKey(key),
            normalizeTranslationReplacements(replacements)
        );

        app.config.globalProperties.$tChoice = (key, number, replacements = {}) => baseTemplateTransChoice(
            normalizeTranslationKey(key),
            normalizeChoiceNumber(number),
            normalizeTranslationReplacements(replacements)
        );
    },
};
