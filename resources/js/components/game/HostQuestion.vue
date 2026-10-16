<script setup lang="ts">
import { Check } from '@lucide/vue';
import { computed } from 'vue';
import CountdownBar from '@/components/game/CountdownBar.vue';
import PlayerAvatar from '@/components/room/PlayerAvatar.vue';
import { useTrans } from '@/composables/useTrans';
import { answerSlots } from '@/lib/answerSlots';
import type { RoomPlayer, RoomRound } from '@/types/room';

const props = defineProps<{
    round: RoomRound;
    players: RoomPlayer[];
    now: number;
}>();

const { t } = useTrans();

const reveal = computed(() => props.round.reveal);
const unlocked = computed(() => props.now >= props.round.opensAt);
const playersById = computed(
    () => new Map(props.players.map((player) => [player.id, player])),
);

function choosers(slot: number): RoomPlayer[] {
    return (reveal.value?.results ?? [])
        .filter((result) => result.slot === slot)
        .map((result) => playersById.value.get(result.playerId))
        .filter((player): player is RoomPlayer => player !== undefined);
}

const fastest = computed(() => {
    const correct = (reveal.value?.results ?? [])
        .filter((result) => result.correct && result.responseMs !== null)
        .sort((a, b) => (a.responseMs ?? 0) - (b.responseMs ?? 0))[0];

    return correct
        ? {
              player: playersById.value.get(correct.playerId),
              seconds: ((correct.responseMs ?? 0) / 1000).toFixed(1),
          }
        : null;
});
</script>

<template>
    <main class="mx-auto flex min-h-dvh max-w-7xl flex-col gap-8 p-6 lg:p-10">
        <header class="flex items-center justify-between gap-4">
            <span
                class="rounded-full px-4 py-1.5 text-lg font-bold"
                :style="{ backgroundColor: round.category.color ?? '#6366f1' }"
            >
                {{ round.category.name }}
            </span>
            <span class="text-xl font-semibold text-white/70 tabular-nums">
                {{
                    t('Question :number of :total', {
                        number: round.number,
                        total: round.total,
                    })
                }}
            </span>
        </header>

        <h1
            class="flex flex-1 items-center justify-center text-center text-4xl leading-tight font-black text-balance lg:text-6xl"
        >
            {{ round.question }}
        </h1>

        <div class="grid gap-4 sm:grid-cols-2">
            <div
                v-for="(option, slot) in round.options"
                :key="slot"
                class="relative flex h-24 items-center gap-4 rounded-3xl px-5 text-2xl font-bold shadow-lg transition duration-500 lg:h-28 lg:text-3xl"
                :class="[
                    answerSlots[slot].bg,
                    reveal && reveal.correctSlot !== slot
                        ? 'scale-95 opacity-30'
                        : '',
                    reveal && reveal.correctSlot === slot
                        ? 'ring-8 ring-white'
                        : '',
                    !reveal && !unlocked ? 'opacity-60' : '',
                ]"
            >
                <component
                    :is="answerSlots[slot].icon"
                    class="size-9 shrink-0 fill-white/90 stroke-white"
                />
                <span class="line-clamp-2 flex-1 leading-tight">{{
                    option
                }}</span>
                <template v-if="reveal">
                    <Check
                        v-if="reveal.correctSlot === slot"
                        class="size-10 shrink-0 stroke-[3]"
                    />
                    <span
                        class="shrink-0 rounded-full bg-black/25 px-3 py-1 text-xl tabular-nums"
                        >{{ reveal.counts[slot] }}</span
                    >
                </template>
                <div
                    v-if="reveal && choosers(slot).length"
                    class="absolute -bottom-3 left-5 flex -space-x-2"
                >
                    <PlayerAvatar
                        v-for="player in choosers(slot)"
                        :key="player.id"
                        :nickname="player.nickname"
                        :color="player.color"
                        size="sm"
                        class="ring-2 ring-white"
                    />
                </div>
            </div>
        </div>

        <footer class="grid h-28 content-center gap-4">
            <template v-if="!reveal">
                <CountdownBar
                    v-if="unlocked"
                    :from="round.opensAt"
                    :to="round.endsAt"
                    :now="now"
                />
                <p
                    v-else
                    class="text-center text-2xl font-semibold text-white/80"
                >
                    {{ t('Read the question…') }}
                </p>
                <div class="flex items-center justify-center gap-3">
                    <span class="text-white/70 tabular-nums">
                        {{
                            t(':count of :total answered', {
                                count: round.answered.length,
                                total: players.length,
                            })
                        }}
                    </span>
                    <div class="flex -space-x-2">
                        <PlayerAvatar
                            v-for="player in players.filter((p) =>
                                round.answered.includes(p.id),
                            )"
                            :key="player.id"
                            :nickname="player.nickname"
                            :color="player.color"
                            size="sm"
                            class="ring-2 ring-indigo-950"
                        />
                    </div>
                </div>
            </template>
            <div v-else class="grid gap-1 text-center">
                <p v-if="reveal.explanation" class="text-xl text-white/80">
                    {{ reveal.explanation }}
                </p>
                <p v-if="fastest?.player" class="text-xl font-semibold">
                    ⚡
                    {{
                        t('Fastest: :name (:seconds s)', {
                            name: fastest.player.nickname,
                            seconds: fastest.seconds,
                        })
                    }}
                </p>
                <p v-else class="text-xl text-white/70">
                    {{ t('Nobody got it right!') }}
                </p>
            </div>
        </footer>
    </main>
</template>
