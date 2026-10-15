<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Check, Crown, LogOut, Pause, Play, WifiOff, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import SwapTransition from '@/components/game/SwapTransition.vue';
import PlayerAnswerPad from '@/components/game/PlayerAnswerPad.vue';
import GameBackground from '@/components/room/GameBackground.vue';
import PlayerAvatar from '@/components/room/PlayerAvatar.vue';
import { useRoom } from '@/composables/useRoom';
import { useTrans } from '@/composables/useTrans';
import { useWakeLock } from '@/composables/useWakeLock';
import { answer, create, leave, start } from '@/routes/play';
import type { RoomPlayer, RoomState } from '@/types/room';

const props = defineProps<{
    room: RoomState;
    me: RoomPlayer;
    kicked: boolean;
    minPlayers: number;
    myAnswer: { round: number; slot: number } | null;
}>();

const { t } = useTrans();
const page = usePage();
const { room, now, connection, hostOnline, presenceReady } = useRoom(
    () => props.room,
    ['room', 'myAnswer', 'kicked'],
);

useWakeLock();

/**
 * The TV dropped out. Until the server notices and pauses the game (a few
 * seconds), a hint; once paused, a countdown to the room closing.
 */
const screenOffline = computed(
    () =>
        !room.value.paused &&
        presenceReady.value &&
        !hostOnline.value &&
        !['lobby', 'finished', 'abandoned'].includes(room.value.status),
);

const closesIn = computed(() => {
    const seconds = Math.max(
        0,
        Math.ceil(((room.value.nextAt ?? now.value) - now.value) / 1000),
    );

    return `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`;
});

const networkError = ref<string | null>(null);

/** My latest details (e.g. becoming VIP), or null once I'm no longer in the room. */
const myself = computed(
    () => room.value.players.find(({ id }) => id === props.me.id) ?? null,
);

const removed = computed(() => props.kicked || myself.value === null);
const closed = computed(() => room.value.status === 'abandoned');
const connectionLost = computed(() =>
    ['reconnecting', 'disconnected', 'failed'].includes(connection.value),
);
const canStart = computed(() => room.value.players.length >= props.minPlayers);
const startError = computed(
    () => (page.props.errors as Record<string, string>).start,
);

// The button pressed this round: known locally straight away, and from the
// server after a reload.
const pending = ref<{ round: number; slot: number } | null>(null);

const chosenSlot = computed(() => {
    const round = room.value.round?.number;

    for (const choice of [pending.value, props.myAnswer]) {
        if (choice && choice.round === round) {
            return choice.slot;
        }
    }

    return null;
});

const answerError = computed(
    () =>
        networkError.value ??
        (page.props.errors as Record<string, string>).answer,
);

function submitAnswer(slot: number) {
    const round = room.value.round?.number;

    if (!round) {
        return;
    }

    pending.value = { round, slot };
    networkError.value = null;
    router.post(
        answer.url(room.value.code),
        { round, slot },
        {
            preserveScroll: true,
            preserveState: true,
            onError: () => (pending.value = null),
            // The answer may or may not have arrived. Sending it again is
            // safe: the server accepts a repeat of the same answer.
            onNetworkError: () => {
                pending.value = null;
                networkError.value = t(
                    'Connection problem. Tap your answer again.',
                );
            },
        },
    );
}

const myResult = computed(
    () =>
        room.value.round?.reveal?.results.find(
            (result) => result.playerId === props.me.id,
        ) ?? null,
);

const myStanding = computed(
    () =>
        room.value.standings?.find(
            (standing) => standing.playerId === props.me.id,
        ) ?? null,
);

function playerName(id: number) {
    return room.value.players.find((p) => p.id === id)?.nickname ?? '?';
}

function playerColor(id: number) {
    return room.value.players.find((p) => p.id === id)?.color ?? '#6366f1';
}

function startGame() {
    router.post(start.url(room.value.code), {}, { preserveScroll: true });
}

function leaveRoom() {
    if (confirm(t('Leave this room?'))) {
        router.post(leave.url(room.value.code));
    }
}
</script>

<template>
    <Head :title="`${t('Room')} ${room.code}`" />

    <GameBackground>
        <div
            v-if="connectionLost"
            class="fixed inset-x-0 top-0 z-50 flex items-center justify-center gap-2 bg-rose-500/90 px-4 py-1.5 text-sm font-medium"
        >
            <WifiOff class="size-4" />
            {{ t('Connection lost. Reconnecting…') }}
        </div>

        <div
            v-else-if="screenOffline"
            class="fixed inset-x-0 top-0 z-50 bg-amber-500/90 px-4 py-1.5 text-center text-sm font-medium text-indigo-950"
        >
            {{ t('The screen is offline. The game carries on.') }}
        </div>

        <main class="mx-auto flex min-h-dvh max-w-md flex-col gap-6 px-4 py-6">
            <!-- Removed or room closed -->
            <section
                v-if="removed || closed"
                class="my-auto grid gap-4 rounded-3xl bg-white/10 p-6 text-center ring-1 ring-white/15"
            >
                <h1 class="text-2xl font-bold">
                    {{
                        closed
                            ? t('The room was closed.')
                            : t('You were removed from the room.')
                    }}
                </h1>
                <Link
                    :href="create()"
                    class="rounded-2xl bg-amber-400 px-6 py-3 text-lg font-black text-indigo-950"
                >
                    {{ t('Join another game') }}
                </Link>
            </section>

            <template v-else-if="myself">
                <header
                    class="flex items-center gap-4 rounded-3xl bg-white/10 p-4 ring-1 ring-white/15"
                >
                    <PlayerAvatar
                        :nickname="myself.nickname"
                        :color="myself.color"
                        size="lg"
                    />
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-2xl font-black">
                            {{ myself.nickname }}
                        </p>
                        <p class="text-sm text-white/70">
                            {{ t('Room') }}
                            <span class="font-mono font-bold tracking-widest">{{
                                room.code
                            }}</span>
                        </p>
                    </div>
                    <span
                        v-if="myself.isVip"
                        class="flex items-center gap-1 rounded-full bg-amber-400/20 px-3 py-1 text-sm font-bold text-amber-300"
                    >
                        <Crown class="size-4" /> VIP
                    </span>
                </header>

                <!-- One phase at a time; paused first: the screen went away. -->
                <SwapTransition>
                    <section
                        v-if="room.paused"
                        key="paused"
                        class="my-auto grid justify-items-center gap-3 rounded-3xl bg-white/10 p-6 text-center ring-1 ring-white/15"
                    >
                        <Pause class="size-12 text-amber-300" />
                        <p class="text-3xl font-black">
                            {{ t('Game paused') }}
                        </p>
                        <p class="text-white/80">
                            {{
                                t(
                                    'The screen disconnected. Waiting for it to come back…',
                                )
                            }}
                        </p>
                        <p class="text-sm text-white/60">
                            {{
                                t('The room closes in :time if it does not.', {
                                    time: closesIn,
                                })
                            }}
                        </p>
                    </section>

                    <!-- Lobby -->
                    <section
                        v-else-if="room.status === 'lobby'"
                        key="lobby"
                        class="grid flex-1 content-start gap-6"
                    >
                        <div
                            class="rounded-3xl bg-white/10 p-6 text-center ring-1 ring-white/15"
                        >
                            <p class="text-3xl font-black">
                                {{ t("You're in!") }}
                            </p>
                            <p class="mt-2 text-white/70">
                                {{
                                    hostOnline
                                        ? t(
                                              'Look at the screen. The game starts soon.',
                                          )
                                        : t(
                                              'Waiting for the screen to reconnect…',
                                          )
                                }}
                            </p>
                        </div>

                        <div v-if="myself.isVip" class="grid gap-2">
                            <button
                                type="button"
                                class="flex items-center justify-center gap-2 rounded-2xl bg-amber-400 px-6 py-4 text-xl font-black text-indigo-950 shadow-lg disabled:opacity-50"
                                :disabled="!canStart"
                                @click="startGame"
                            >
                                <Play class="size-6 fill-current" />
                                {{ t('Start game') }}
                            </button>
                            <p
                                class="text-center text-sm"
                                :class="
                                    startError
                                        ? 'text-rose-300'
                                        : 'text-white/70'
                                "
                            >
                                {{
                                    startError ??
                                    (canStart
                                        ? t(
                                              "You're the VIP: start when everyone is in.",
                                          )
                                        : t(
                                              'Waiting for at least :count players…',
                                              { count: minPlayers },
                                          ))
                                }}
                            </p>
                        </div>

                        <div class="grid gap-2">
                            <h2 class="text-sm font-medium text-white/70">
                                {{ t('Players') }} ·
                                {{ room.players.length }}/{{
                                    room.settings.max_players
                                }}
                            </h2>
                            <ul class="flex flex-wrap gap-2">
                                <li
                                    v-for="player in room.players"
                                    :key="player.id"
                                    class="flex items-center gap-2 rounded-full bg-white/10 py-1 pr-3 pl-1"
                                >
                                    <PlayerAvatar
                                        :nickname="player.nickname"
                                        :color="player.color"
                                        size="sm"
                                    />
                                    <span class="text-sm font-semibold">{{
                                        player.nickname
                                    }}</span>
                                </li>
                            </ul>
                        </div>
                    </section>

                    <section
                        v-else-if="room.status === 'intro'"
                        key="intro"
                        class="my-auto grid gap-2 text-center"
                    >
                        <p class="text-white/70">{{ t('Get ready!') }}</p>
                        <p class="text-4xl font-black">
                            {{ t('The game is starting') }}
                        </p>
                        <p class="text-white/70">
                            {{ t('Keep your eyes on the screen.') }}
                        </p>
                    </section>

                    <PlayerAnswerPad
                        v-else-if="room.status === 'question' && room.round"
                        :key="`question-${room.round.number}`"
                        :round="room.round"
                        :now="now"
                        :chosen="chosenSlot"
                        :error="answerError"
                        @answer="submitAnswer"
                    />

                    <!-- Reveal: how did I do? -->
                    <section
                        v-else-if="
                            room.status === 'reveal' && room.round?.reveal
                        "
                        :key="`reveal-${room.round.number}`"
                        class="my-auto grid justify-items-center gap-4 text-center"
                    >
                        <template v-if="myResult?.correct">
                            <div
                                class="flex size-28 items-center justify-center rounded-full bg-emerald-500 shadow-xl"
                            >
                                <Check class="size-16 stroke-[3]" />
                            </div>
                            <p class="text-4xl font-black">
                                {{ t('Correct!') }}
                            </p>
                            <p
                                class="text-3xl font-black text-emerald-300 tabular-nums"
                            >
                                +{{ myResult.points }}
                            </p>
                        </template>
                        <template v-else>
                            <div
                                class="flex size-28 items-center justify-center rounded-full bg-rose-500 shadow-xl"
                            >
                                <X class="size-16 stroke-[3]" />
                            </div>
                            <p class="text-4xl font-black">
                                {{
                                    myResult?.slot === null || !myResult
                                        ? t('No answer')
                                        : t('Not quite!')
                                }}
                            </p>
                            <p class="text-white/80">
                                {{ t('The answer was:') }}
                                <span class="font-bold">{{
                                    room.round.options[
                                        room.round.reveal.correctSlot
                                    ]
                                }}</span>
                            </p>
                        </template>
                    </section>

                    <!-- Scoreboard and final results: where do I stand? -->
                    <section
                        :key="`${room.status}-${room.round?.number}`"
                        v-else-if="
                            (room.status === 'scoreboard' ||
                                room.status === 'finished') &&
                            myStanding
                        "
                        class="my-auto grid justify-items-center gap-3 text-center"
                    >
                        <p class="text-white/70">
                            {{
                                room.status === 'finished'
                                    ? t('Final results')
                                    : t('Scoreboard')
                            }}
                        </p>
                        <p
                            class="text-8xl font-black text-amber-300 tabular-nums"
                        >
                            #{{ myStanding.rank }}
                        </p>
                        <p class="text-2xl font-bold tabular-nums">
                            {{
                                t(':score points', { score: myStanding.score })
                            }}
                        </p>
                        <p
                            v-if="room.status === 'finished'"
                            class="text-white/70"
                        >
                            {{
                                myStanding.rank === 1
                                    ? t('You won! 🎉')
                                    : t('Thanks for playing!')
                            }}
                        </p>
                        <ol
                            v-if="room.status === 'finished'"
                            class="mt-4 grid w-full gap-2"
                        >
                            <li
                                v-for="standing in room.standings?.slice(0, 5)"
                                :key="standing.playerId"
                                class="flex items-center gap-3 rounded-2xl bg-white/10 p-2 pr-4"
                                :class="{
                                    'ring-2 ring-amber-300':
                                        standing.playerId === me.id,
                                }"
                            >
                                <span
                                    class="w-6 text-center font-black tabular-nums"
                                    >{{ standing.rank }}</span
                                >
                                <PlayerAvatar
                                    :nickname="playerName(standing.playerId)"
                                    :color="playerColor(standing.playerId)"
                                    size="sm"
                                />
                                <span
                                    class="flex-1 truncate text-left font-semibold"
                                    >{{ playerName(standing.playerId) }}</span
                                >
                                <span class="font-bold tabular-nums">{{
                                    standing.score
                                }}</span>
                            </li>
                        </ol>
                        <Link
                            v-if="room.status === 'finished'"
                            :href="create()"
                            class="mt-4 rounded-2xl bg-amber-400 px-6 py-3 text-lg font-black text-indigo-950"
                        >
                            {{ t('Join another game') }}
                        </Link>
                    </section>
                </SwapTransition>

                <button
                    v-if="room.status === 'lobby'"
                    type="button"
                    class="mt-auto flex items-center justify-center gap-2 py-2 text-sm text-white/50 hover:text-white"
                    @click="leaveRoom"
                >
                    <LogOut class="size-4" /> {{ t('Leave room') }}
                </button>
            </template>
        </main>
    </GameBackground>
</template>
