<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    Clock,
    Hourglass,
    Lock,
    RotateCcw,
    SearchX,
    Smartphone,
    TriangleAlert,
    Wrench,
} from '@lucide/vue';
import { computed } from 'vue';
import GameBackground from '@/components/room/GameBackground.vue';
import { useTrans } from '@/composables/useTrans';
import { home } from '@/routes';
import { create as join } from '@/routes/play';

/** Shown instead of Laravel's error pages (see bootstrap/app.php). */
const props = defineProps<{ status: number }>();

const { t } = useTrans();

const error = computed(() => {
    switch (props.status) {
        case 403:
            return {
                icon: Lock,
                title: t('This page isn’t for you'),
                message: t(
                    'Only the screen or the players of a room can open it.',
                ),
            };
        case 404:
        case 405:
            return {
                icon: SearchX,
                title: t('Nothing here'),
                message: t('The room may have closed, or the link is wrong.'),
            };
        case 419:
            return {
                icon: Clock,
                title: t('This page expired'),
                message: t(
                    'It was open for a long time. Reload it and try again.',
                ),
            };
        case 429:
            return {
                icon: Hourglass,
                title: t('Slow down a little'),
                message: t(
                    'Too many tries in a short time. Wait a moment and try again.',
                ),
            };
        case 503:
            return {
                icon: Wrench,
                title: t('Quizly is being updated'),
                message: t('It’ll be back in a moment.'),
            };
        default:
            return {
                icon: TriangleAlert,
                title: t('Something went wrong'),
                message: t('Please try again in a moment.'),
            };
    }
});

function reload() {
    window.location.reload();
}
</script>

<template>
    <Head :title="error.title" />

    <GameBackground>
        <main
            class="mx-auto flex min-h-dvh max-w-md flex-col items-center justify-center gap-6 px-4 py-12 text-center"
        >
            <component :is="error.icon" class="size-16 text-amber-300" />
            <div class="grid gap-2">
                <h1 class="text-4xl font-black">{{ error.title }}</h1>
                <p class="text-lg text-white/70">{{ error.message }}</p>
            </div>

            <div class="grid w-full gap-3">
                <button
                    v-if="status === 419 || status === 503"
                    type="button"
                    class="flex items-center justify-center gap-2 rounded-2xl bg-amber-400 px-6 py-4 text-xl font-black text-indigo-950 shadow-lg transition hover:bg-amber-300"
                    @click="reload"
                >
                    <RotateCcw class="size-5" />
                    {{ t('Reload') }}
                </button>
                <Link
                    v-if="status === 404 || status === 405"
                    :href="join()"
                    class="flex items-center justify-center gap-2 rounded-2xl bg-amber-400 px-6 py-4 text-xl font-black text-indigo-950 shadow-lg transition hover:bg-amber-300"
                >
                    <Smartphone class="size-5" />
                    {{ t('Join a game') }}
                </Link>
                <Link
                    :href="home()"
                    class="rounded-2xl bg-white/10 px-6 py-3 font-semibold ring-1 ring-white/15 transition hover:bg-white/20"
                >
                    {{ t('Back to the start') }}
                </Link>
            </div>
        </main>
    </GameBackground>
</template>
