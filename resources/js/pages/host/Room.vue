<script setup lang="ts">
import { Form, Head, router, usePage } from '@inertiajs/vue3';
import {
    Crown,
    Play,
    RotateCcw,
    SkipForward,
    Users,
    WifiOff,
} from '@lucide/vue';
import { useEventListener, useIntervalFn } from '@vueuse/core';
import { computed, watch } from 'vue';
import HostFinal from '@/components/game/HostFinal.vue';
import HostIntro from '@/components/game/HostIntro.vue';
import HostQuestion from '@/components/game/HostQuestion.vue';
import HostScoreboard from '@/components/game/HostScoreboard.vue';
import GameBackground from '@/components/room/GameBackground.vue';
import HostSettings from '@/components/room/HostSettings.vue';
import JoinQrCode from '@/components/room/JoinQrCode.vue';
import PlayerCard from '@/components/room/PlayerCard.vue';
import { useRoom } from '@/composables/useRoom';
import { useTrans } from '@/composables/useTrans';
import { useWakeLock } from '@/composables/useWakeLock';
import { postJson } from '@/lib/http';
import {
    close,
    heartbeat as heartbeatRoute,
    kick,
    next,
    presence,
    start,
    store as createRoom,
} from '@/routes/host/rooms';
import type {
    CategoryOption,
    Range,
    RoomPlayer,
    RoomState,
} from '@/types/room';

const props = defineProps<{
    room: RoomState;
    categoryOptions: CategoryOption[];
    joinUrl: string;
    limits: { rounds: Range; secondsPerRound: Range; players: Range };
    locales: string[];
    disconnectGraceSeconds: number;
    heartbeatSeconds: number;
}>();

const { t } = useTrans();
const page = usePage();
const { room, now, isOnline, connection, presenceReady } = useRoom(
    () => props.room,
    ['room'],
    () => {
        // Someone dropped out mid-question: maybe everyone left has answered.
        if (room.value.status === 'question') {
            void postJson(presence.url(room.value.code));
        }
    },
);

useWakeLock();

// Tell the server the screen is still here. If the heartbeats stop, the
// server pauses the game (players can't see the questions without the
// screen) and closes the room if the screen doesn't come back in time.
function heartbeat() {
    if (!['finished', 'abandoned'].includes(room.value.status)) {
        void postJson(heartbeatRoute.url(room.value.code));
    }
}

useIntervalFn(heartbeat, () => props.heartbeatSeconds * 1000);
useEventListener(document, 'visibilitychange', () => {
    if (document.visibilityState === 'visible') {
        heartbeat();
    }
});
watch(connection, (status) => status === 'connected' && heartbeat());

// Closing a tab isn't leaving, so a player whose phone goes away keeps their
// place for a while (a refresh takes a second or two). After the grace period
// the TV asks the server to remove them from the lobby, freeing the slot and
// handing VIP on. The server checks with Reverb before removing anyone.
const offlineSince = new Map<number, number>();
let lastRemovalRequestAt = 0;

useIntervalFn(() => {
    if (room.value.status !== 'lobby' || !presenceReady.value) {
        offlineSince.clear();

        return;
    }

    const current = Date.now();
    const playerIds = new Set(room.value.players.map(({ id }) => id));

    for (const id of offlineSince.keys()) {
        if (!playerIds.has(id) || isOnline(id)) {
            offlineSince.delete(id);
        }
    }

    for (const id of playerIds) {
        if (!isOnline(id) && !offlineSince.has(id)) {
            offlineSince.set(id, current);
        }
    }

    const gone = [...offlineSince]
        .filter(
            ([, since]) =>
                current - since > props.disconnectGraceSeconds * 1000,
        )
        .map(([id]) => id);

    if (gone.length && current - lastRemovalRequestAt > 5_000) {
        lastRemovalRequestAt = current;
        void postJson(presence.url(room.value.code), { gone });
    }
}, 1_000);

const connectionLost = computed(() =>
    ['reconnecting', 'disconnected', 'failed'].includes(connection.value),
);

const joinHost = computed(() => {
    const url = new URL(props.joinUrl);

    return `${url.host}/play`;
});

const emptySlots = computed(() =>
    Math.max(0, room.value.settings.max_players - room.value.players.length),
);

const availableQuestions = computed(() =>
    props.categoryOptions
        .filter((category) =>
            room.value.categories.some(({ id }) => id === category.id),
        )
        .reduce(
            (sum, category) =>
                sum + (category.questionCounts[room.value.locale] ?? 0),
            0,
        ),
);

const startBlocker = computed(() => {
    if (room.value.players.length < props.limits.players.min) {
        return t('Waiting for at least :count players…', {
            count: props.limits.players.min,
        });
    }

    if (availableQuestions.value < room.value.settings.rounds) {
        return t('Not enough questions for :rounds rounds.', {
            rounds: room.value.settings.rounds,
        });
    }

    return null;
});

const startError = computed(
    () => (page.props.errors as Record<string, string>).start,
);

function startGame() {
    router.post(start.url(room.value.code), {}, { preserveScroll: true });
}

function skip() {
    router.post(next.url(room.value.code), {}, { preserveScroll: true });
}

function removePlayer(player: RoomPlayer) {
    if (confirm(t('Remove :name from the room?', { name: player.nickname }))) {
        router.post(
            kick.url({ room: room.value.code, player: player.id }),
            {},
            { preserveScroll: true },
        );
    }
}

function closeRoom() {
    if (confirm(t('Close this room? Everyone will be disconnected.'))) {
        router.post(close.url(room.value.code));
    }
}
</script>

<template>
    <Head :title="`${t('Room')} ${room.code}`" />

    <GameBackground>
        <div
            v-if="connectionLost"
            class="flex items-center justify-center gap-2 bg-rose-500/90 px-4 py-1.5 text-sm font-medium"
        >
            <WifiOff class="size-4" />
            {{ t('Connection lost. Reconnecting…') }}
        </div>

        <!-- Lobby -->
        <main
            v-if="room.status === 'lobby'"
            class="mx-auto grid max-w-7xl gap-8 p-6 lg:grid-cols-[1fr_24rem] lg:p-10"
        >
            <section class="grid content-start gap-8">
                <div
                    class="flex flex-col items-center gap-6 rounded-3xl bg-white/10 p-6 ring-1 ring-white/15 sm:flex-row sm:items-center lg:p-8"
                >
                    <JoinQrCode :url="joinUrl" />
                    <div class="text-center sm:text-left">
                        <p class="text-lg text-white/80">
                            {{ t('Join on your phone at') }}
                            <span class="font-semibold text-white">{{
                                joinHost
                            }}</span>
                        </p>
                        <p
                            class="mt-1 text-sm tracking-widest text-white/60 uppercase"
                        >
                            {{ t('Room code') }}
                        </p>
                        <p
                            class="font-mono text-7xl font-black tracking-[0.2em] text-amber-300 drop-shadow lg:text-8xl"
                        >
                            {{ room.code }}
                        </p>
                    </div>
                </div>

                <div class="grid gap-4">
                    <h2 class="flex items-center gap-2 text-xl font-bold">
                        <Users class="size-5" />
                        {{ t('Players') }}
                        <span class="text-white/60 tabular-nums"
                            >{{ room.players.length }}/{{
                                room.settings.max_players
                            }}</span
                        >
                    </h2>
                    <TransitionGroup
                        tag="div"
                        class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3"
                        enter-from-class="scale-90 opacity-0"
                        enter-active-class="transition duration-300"
                        leave-to-class="scale-90 opacity-0"
                        leave-active-class="transition duration-200"
                    >
                        <PlayerCard
                            v-for="player in room.players"
                            :key="player.id"
                            :player="player"
                            :online="isOnline(player.id)"
                            removable
                            @remove="removePlayer(player)"
                        />
                        <div
                            v-for="slot in emptySlots"
                            :key="`empty-${slot}`"
                            class="flex h-[4.5rem] items-center justify-center rounded-2xl border-2 border-dashed border-white/15 text-sm text-white/40"
                        >
                            {{ t('Waiting for player…') }}
                        </div>
                    </TransitionGroup>
                </div>
            </section>

            <aside
                class="grid content-start gap-6 rounded-3xl bg-white/10 p-6 ring-1 ring-white/15"
            >
                <h2 class="text-xl font-bold">{{ t('Game settings') }}</h2>

                <HostSettings
                    :room="room"
                    :category-options="categoryOptions"
                    :limits="limits"
                    :locales="locales"
                />

                <div class="grid gap-2 border-t border-white/15 pt-6">
                    <button
                        type="button"
                        class="flex items-center justify-center gap-2 rounded-2xl bg-amber-400 px-6 py-4 text-xl font-black text-indigo-950 shadow-lg transition hover:bg-amber-300 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="startBlocker !== null"
                        @click="startGame"
                    >
                        <Play class="size-6 fill-current" />
                        {{ t('Start game') }}
                    </button>
                    <p
                        v-if="startError || startBlocker"
                        class="text-center text-sm"
                        :class="startError ? 'text-rose-300' : 'text-white/70'"
                    >
                        {{ startError ?? startBlocker }}
                    </p>
                    <p
                        class="flex items-center justify-center gap-1 text-xs text-white/50"
                    >
                        <Crown class="size-3.5 text-amber-300" />
                        {{
                            t(
                                'The VIP can also start the game from their phone.',
                            )
                        }}
                    </p>
                    <button
                        type="button"
                        class="mt-2 text-sm text-white/50 underline-offset-4 hover:text-white hover:underline"
                        @click="closeRoom"
                    >
                        {{ t('Close room') }}
                    </button>
                </div>
            </aside>
        </main>

        <template v-else>
            <HostIntro v-if="room.status === 'intro'" :room="room" :now="now" />
            <HostQuestion
                v-else-if="
                    (room.status === 'question' || room.status === 'reveal') &&
                    room.round
                "
                :round="room.round"
                :players="room.players"
                :now="now"
            />
            <HostScoreboard
                v-else-if="room.status === 'scoreboard'"
                :room="room"
            />
            <HostFinal v-else-if="room.status === 'finished'" :room="room" />
            <main
                v-else-if="room.status === 'abandoned'"
                class="flex min-h-dvh flex-col items-center justify-center gap-4 p-10 text-center"
            >
                <h1 class="text-5xl font-black">
                    {{ t('The room was closed.') }}
                </h1>
                <p class="text-white/70">
                    {{ t('Start a new game to play again.') }}
                </p>
            </main>

            <!-- Host controls, kept small and out of the way. -->
            <div class="fixed right-4 bottom-4 flex items-center gap-2 text-sm">
                <template
                    v-if="
                        room.status === 'finished' ||
                        room.status === 'abandoned'
                    "
                >
                    <!-- A new room needs a fresh page, not this one's state. -->
                    <Form v-bind="createRoom.form()" :preserve-state="false">
                        <button
                            type="submit"
                            class="flex items-center gap-2 rounded-full bg-amber-400 px-5 py-2.5 font-bold text-indigo-950 shadow-lg hover:bg-amber-300"
                        >
                            <RotateCcw class="size-4" /> {{ t('New game') }}
                        </button>
                    </Form>
                </template>
                <template v-else>
                    <button
                        type="button"
                        class="flex items-center gap-1.5 rounded-full bg-white/10 px-4 py-2 font-semibold ring-1 ring-white/20 backdrop-blur hover:bg-white/20"
                        @click="skip"
                    >
                        <SkipForward class="size-4" /> {{ t('Skip') }}
                    </button>
                    <button
                        type="button"
                        class="rounded-full px-3 py-2 text-white/50 hover:text-white"
                        @click="closeRoom"
                    >
                        {{ t('Close room') }}
                    </button>
                </template>
            </div>
        </template>
    </GameBackground>
</template>
