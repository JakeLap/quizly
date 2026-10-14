import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * UI strings from lang/{locale}.json, shared by the server. English strings
 * are their own keys, so a missing translation shows the English text.
 */
export function useTrans() {
    const page = usePage();
    const translations = computed(() => page.props.translations ?? {});

    function t(key: string, replace: Record<string, string | number> = {}) {
        let text = translations.value[key] ?? key;

        for (const [name, value] of Object.entries(replace)) {
            text = text.replaceAll(`:${name}`, String(value));
        }

        return text;
    }

    return { t, locale: computed(() => page.props.locale) };
}
