<script setup lang="ts">
import { Crown } from '@lucide/vue';
import { computed } from 'vue';
import PlayerAvatar from '@/components/room/PlayerAvatar.vue';
import { useTrans } from '@/composables/useTrans';
import type { RoomState } from '@/types/room';

const props = defineProps<{ room: RoomState }>();

const { t } = useTrans();

const rows = computed(() => {
    const players = new Map(props.room.players.map((p) => [p.id, p]));

    return (props.room.standings ?? []).map((standing) => ({
        ...standing,
        player: players.get(standing.playerId),
    }));
});

// Podium order: 2nd, 1st, 3rd.
const podium = computed(() =>
    [rows.value[1], rows.value[0], rows.value[2]].filter(Boolean),
);
const heights: Record<number, string> = { 1: 'h-48', 2: 'h-36', 3: 'h-28' };
</script>

<template>
    <main
        class="mx-auto flex min-h-dvh max-w-5xl flex-col items-center justify-center gap-10 p-6 lg:p-10"
    >
        <h1 class="text-center text-5xl font-black lg:text-7xl">
            {{ t('Final results') }}
        </h1>

        <div class="flex items-end justify-center gap-4">
            <div
                v-for="row in podium"
                :key="row.playerId"
                class="flex w-40 flex-col items-center gap-2 lg:w-52"
            >
                <Crown v-if="row.rank === 1" class="size-10 text-amber-300" />
                <PlayerAvatar
                    v-if="row.player"
                    :nickname="row.player.nickname"
                    :color="row.player.color"
                    size="lg"
                />
                <p class="max-w-full truncate text-2xl font-black">
                    {{ row.player?.nickname }}
                </p>
                <p class="text-lg font-bold text-white/80 tabular-nums">
                    {{ row.score }}
                </p>
                <div
                    class="flex w-full items-start justify-center rounded-t-2xl pt-3 text-5xl font-black"
                    :class="[
                        heights[row.rank] ?? 'h-24',
                        row.rank === 1
                            ? 'bg-amber-400 text-indigo-950'
                            : 'bg-white/15',
                    ]"
                >
                    {{ row.rank }}
                </div>
            </div>
        </div>

        <ol v-if="rows.length > 3" class="grid w-full max-w-xl gap-2">
            <li
                v-for="row in rows.slice(3)"
                :key="row.playerId"
                class="flex items-center gap-3 rounded-2xl bg-white/10 p-2 pr-4"
            >
                <span class="w-8 text-center text-xl font-black tabular-nums">{{
                    row.rank
                }}</span>
                <PlayerAvatar
                    v-if="row.player"
                    :nickname="row.player.nickname"
                    :color="row.player.color"
                    size="sm"
                />
                <span class="flex-1 truncate font-semibold">{{
                    row.player?.nickname
                }}</span>
                <span class="font-bold tabular-nums">{{ row.score }}</span>
            </li>
        </ol>
    </main>
</template>
