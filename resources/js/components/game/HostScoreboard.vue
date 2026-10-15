<script setup lang="ts">
import { computed, nextTick, onMounted, ref } from 'vue';
import AnimatedNumber from '@/components/game/AnimatedNumber.vue';
import PlayerAvatar from '@/components/room/PlayerAvatar.vue';
import { useTrans } from '@/composables/useTrans';
import { animate } from '@/lib/motion';
import type { RoomState } from '@/types/room';

const props = defineProps<{ room: RoomState }>();

const { t } = useTrans();

/**
 * The board first shows the standings as they were before this question,
 * then (once on screen) slides everyone to their new place while the scores
 * count up.
 */
const settled = ref(false);
const list = ref<HTMLOListElement>();

onMounted(() => window.setTimeout(settle, 800));

/**
 * Move each row to its new place: note where the rows are, re-order them,
 * then animate each from its old position to its new one ("FLIP"). If the
 * animation can't run, the rows are simply in their new places.
 */
async function settle() {
    const rowsBefore = new Map(
        [...(list.value?.children ?? [])].map((row) => [
            (row as HTMLElement).dataset.player,
            row.getBoundingClientRect().top,
        ]),
    );

    settled.value = true;
    await nextTick();

    for (const row of list.value?.children ?? []) {
        const before = rowsBefore.get((row as HTMLElement).dataset.player);
        const offset =
            before === undefined ? 0 : before - row.getBoundingClientRect().top;

        if (offset !== 0) {
            animate(
                row,
                [
                    { transform: `translateY(${offset}px)` },
                    { transform: 'none' },
                ],
                { duration: 700, easing: 'ease-in-out' },
            );
        }
    }
}

const rows = computed(() => {
    const players = new Map(props.room.players.map((p) => [p.id, p]));
    const gained = new Map(
        (props.room.round?.reveal?.results ?? []).map((r) => [
            r.playerId,
            r.points,
        ]),
    );

    return (props.room.standings ?? []).map((standing) => ({
        ...standing,
        player: players.get(standing.playerId),
        gained: gained.get(standing.playerId) ?? 0,
        before: standing.score - (gained.get(standing.playerId) ?? 0),
    }));
});

const previousRanks = computed(() => {
    const ordered = [...rows.value].sort((a, b) => b.before - a.before);

    return new Map(
        ordered.map((row) => [
            row.playerId,
            ordered.findIndex((other) => other.before === row.before) + 1,
        ]),
    );
});

const displayed = computed(() => {
    const top = Math.max(
        1,
        ...rows.value.map((row) => (settled.value ? row.score : row.before)),
    );
    const ordered = settled.value
        ? rows.value
        : [...rows.value].sort((a, b) => b.before - a.before);

    return ordered.map((row) => ({
        ...row,
        shownRank: settled.value
            ? row.rank
            : (previousRanks.value.get(row.playerId) ?? row.rank),
        shownScore: settled.value ? row.score : row.before,
        width: Math.max(
            4,
            ((settled.value ? row.score : row.before) / top) * 100,
        ),
    }));
});
</script>

<template>
    <main
        class="mx-auto flex min-h-dvh max-w-5xl flex-col justify-center gap-6 p-6 lg:p-10"
    >
        <h1 class="text-center text-5xl font-black">{{ t('Scoreboard') }}</h1>
        <p v-if="room.round" class="text-center text-white/70">
            {{
                t('After question :number of :total', {
                    number: room.round.number,
                    total: room.round.total,
                })
            }}
        </p>

        <ol ref="list" class="grid gap-3">
            <li
                v-for="row in displayed"
                :key="row.playerId"
                :data-player="row.playerId"
                class="flex h-[4.5rem] items-center gap-4 rounded-2xl bg-white/10 px-3 pr-5"
            >
                <span
                    class="w-10 text-center text-3xl font-black tabular-nums"
                    >{{ row.shownRank }}</span
                >
                <PlayerAvatar
                    v-if="row.player"
                    :nickname="row.player.nickname"
                    :color="row.player.color"
                />
                <div class="min-w-0 flex-1">
                    <p class="truncate text-xl font-bold">
                        {{ row.player?.nickname }}
                    </p>
                    <div class="mt-1 h-2 rounded-full bg-white/10">
                        <div
                            class="h-full rounded-full transition-[width] duration-700 ease-out"
                            :style="{
                                width: `${row.width}%`,
                                backgroundColor: row.player?.color,
                            }"
                        />
                    </div>
                </div>
                <span
                    class="w-16 text-right text-lg font-bold text-emerald-300 tabular-nums transition-opacity duration-500"
                    :class="settled && row.gained ? 'opacity-100' : 'opacity-0'"
                    >+{{ row.gained }}</span
                >
                <AnimatedNumber
                    :value="row.shownScore"
                    class="w-24 text-right text-2xl font-black"
                />
            </li>
        </ol>
    </main>
</template>
