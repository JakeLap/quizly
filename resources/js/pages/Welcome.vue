<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { Smartphone, Tv } from '@lucide/vue';
import GameBackground from '@/components/room/GameBackground.vue';
import { useTrans } from '@/composables/useTrans';
import { dashboard, login } from '@/routes';
import { store as createRoom } from '@/routes/host/rooms';
import { create as join } from '@/routes/play';

const { t } = useTrans();
</script>

<template>
    <Head :title="t('Trivia party game')" />

    <GameBackground>
        <main
            class="mx-auto flex min-h-dvh max-w-4xl flex-col items-center justify-center gap-12 px-4 py-12 text-center"
        >
            <div>
                <h1 class="text-7xl font-black tracking-tight sm:text-8xl">
                    Quiz<span class="text-amber-300">ly</span>
                </h1>
                <p class="mt-4 text-xl text-white/80">
                    {{
                        t(
                            'The trivia party game. One screen, everyone plays on their phone.',
                        )
                    }}
                </p>
            </div>

            <div class="grid w-full max-w-2xl gap-4 sm:grid-cols-2">
                <Form v-bind="createRoom.form()" v-slot="{ processing }">
                    <button
                        type="submit"
                        class="flex h-full w-full flex-col items-center gap-3 rounded-3xl bg-amber-400 p-8 text-indigo-950 shadow-xl transition hover:-translate-y-0.5 hover:bg-amber-300 disabled:opacity-60"
                        :disabled="processing"
                    >
                        <Tv class="size-12" />
                        <span class="text-2xl font-black">{{
                            t('Host a game')
                        }}</span>
                        <span class="text-sm opacity-80">{{
                            t(
                                'Open this on the TV or a laptop everyone can see.',
                            )
                        }}</span>
                    </button>
                </Form>

                <Link
                    :href="join()"
                    class="flex flex-col items-center gap-3 rounded-3xl bg-white/10 p-8 ring-1 ring-white/20 transition hover:-translate-y-0.5 hover:bg-white/15"
                >
                    <Smartphone class="size-12" />
                    <span class="text-2xl font-black">{{
                        t('Join a game')
                    }}</span>
                    <span class="text-sm text-white/70">{{
                        t('Got a room code? Join from your phone.')
                    }}</span>
                </Link>
            </div>

            <Link
                :href="$page.props.auth.user ? dashboard() : login()"
                class="text-sm text-white/40 hover:text-white/70"
            >
                {{ t('Admin') }}
            </Link>
        </main>
    </GameBackground>
</template>
