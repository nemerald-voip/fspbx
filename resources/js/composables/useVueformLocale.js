import { computed } from 'vue';
import { currentLocale } from '@i18n';

export function useVueformLocale() {
    return computed(() => {
        const locale = currentLocale.value;
        if (locale.startsWith('es')) return 'es-419';
        if (locale.startsWith('fr')) return 'fr';
        if (locale.startsWith('ru')) return 'ru';
        if (locale === 'pt-br') return 'pt-br';
        return 'en';
    });
}
