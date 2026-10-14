<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import GameBackground from '@/components/room/GameBackground.vue';
import { useTrans } from '@/composables/useTrans';
import { store } from '@/routes/play';

defineProps<{ code: string | null }>();

const { t } = useTrans();

function toCode(event: Event) {
    const input = event.target as HTMLInputElement;
    input.value = input.value.toUpperCase().replace(/[^A-Z]/g, '');
}
</script>

<template>
    <Head :title="t('Join a game')" />

    <GameBackground>
        <main
            class="mx-auto flex min-h-dvh max-w-sm flex-col justify-center gap-8 px-4 py-10"
        >
            <div class="text-center">
                <h1 class="text-5xl font-black tracking-tight">
                    Quiz<span class="text-amber-300">ly</span>
                </h1>
                <p class="mt-2 text-white/70">
                    {{ t('Enter the code shown on the screen.') }}
                </p>
            </div>

            <Form
                v-bind="store.form()"
                v-slot="{ errors, processing }"
                class="grid gap-5 rounded-3xl bg-white/10 p-6 ring-1 ring-white/15"
            >
                <div class="grid gap-2">
                    <label
                        for="code"
                        class="text-sm font-medium text-white/80"
                        >{{ t('Room code') }}</label
                    >
                    <input
                        id="code"
                        name="code"
                        :value="code ?? ''"
                        maxlength="4"
                        autocomplete="off"
                        autocapitalize="characters"
                        spellcheck="false"
                        required
                        :autofocus="!code"
                        class="w-full rounded-2xl bg-white px-4 py-3 text-center font-mono text-4xl font-black tracking-[0.4em] text-indigo-950 uppercase placeholder:text-indigo-950/20 focus:ring-4 focus:ring-amber-300 focus:outline-none"
                        placeholder="ABCD"
                        @input="toCode"
                    />
                    <p v-if="errors.code" class="text-sm text-rose-300">
                        {{ errors.code }}
                    </p>
                </div>

                <div class="grid gap-2">
                    <label
                        for="nickname"
                        class="text-sm font-medium text-white/80"
                        >{{ t('Nickname') }}</label
                    >
                    <input
                        id="nickname"
                        name="nickname"
                        maxlength="16"
                        autocomplete="nickname"
                        required
                        :autofocus="!!code"
                        class="w-full rounded-2xl bg-white px-4 py-3 text-xl font-semibold text-indigo-950 placeholder:text-indigo-950/30 focus:ring-4 focus:ring-amber-300 focus:outline-none"
                        :placeholder="t('What should we call you?')"
                    />
                    <p v-if="errors.nickname" class="text-sm text-rose-300">
                        {{ errors.nickname }}
                    </p>
                </div>

                <button
                    type="submit"
                    class="rounded-2xl bg-amber-400 px-6 py-4 text-xl font-black text-indigo-950 shadow-lg transition hover:bg-amber-300 disabled:opacity-60"
                    :disabled="processing"
                >
                    {{ processing ? t('Joining…') : t('Join') }}
                </button>
            </Form>
        </main>
    </GameBackground>
</template>
