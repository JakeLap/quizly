<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { useTrans } from '@/composables/useTrans';
import { update } from '@/routes/locale';

/** Each language named in itself, so anyone can find their own. */
const languages = [
    { locale: 'el', label: 'ΕΛ', name: 'Ελληνικά' },
    { locale: 'en', label: 'EN', name: 'English' },
];

const { locale } = useTrans();

function choose(chosen: string) {
    if (chosen !== locale.value) {
        router.post(update.url(), { locale: chosen }, { preserveScroll: true });
    }
}
</script>

<template>
    <div
        class="inline-flex rounded-full bg-white/10 p-1 text-sm font-semibold ring-1 ring-white/15 backdrop-blur"
        role="group"
        aria-label="Language / Γλώσσα"
    >
        <button
            v-for="language in languages"
            :key="language.locale"
            type="button"
            :lang="language.locale"
            :title="language.name"
            :aria-pressed="language.locale === locale"
            class="rounded-full px-3 py-1 transition"
            :class="
                language.locale === locale
                    ? 'bg-white text-indigo-950 shadow'
                    : 'text-white/70 hover:text-white'
            "
            @click="choose(language.locale)"
        >
            {{ language.label }}
            <span class="sr-only">{{ language.name }}</span>
        </button>
    </div>
</template>
