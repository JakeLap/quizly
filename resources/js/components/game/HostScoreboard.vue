<script setup lang="ts">
import { computed } from 'vue';
import PlayerAvatar from '@/components/room/PlayerAvatar.vue';
import { useTrans } from '@/composables/useTrans';
import type { RoomState } from '@/types/room';

const props = defineProps<{ room: RoomState }>();

const { t } = useTrans();

const rows = computed(() => {
    const players = new Map(props.room.players.map((p) => [p.id, p]));
    const gained = new Map(
        (props.room.round?.reveal?.results ?? []).map((r) => [
            r.playerId,
            r.points,
        ]),
    );
    const top = Math.max(
        1,
        ...(props.room.standings ?? []).map((s) => s.score),
    );

    return (props.room.standings ?? []).map((standing) => ({
        ...standing,
        player: players.get(standing.playerId),
        gained: gained.get(standing.playerId) ?? 0,
        width: Math.max(4, (standing.score / top) * 100),
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

        <TransitionGroup
            tag="ol"
            class="grid gap-3"
            move-class="transition duration-700"
        >
            <li
                v-for="row in rows"
                :key="row.playerId"
                class="flex items-center gap-4 rounded-2xl bg-white/10 p-3 pr-5"
            >
                <span
                    class="w-10 text-center text-3xl font-black tabular-nums"
                    >{{ row.rank }}</span
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
                            class="h-full rounded-full transition-[width] duration-700"
                            :style="{
                                width: `${row.width}%`,
                                backgroundColor: row.player?.color,
                            }"
                        />
                    </div>
                </div>
                <span
                    v-if="row.gained"
                    class="text-lg font-bold text-emerald-300 tabular-nums"
                    >+{{ row.gained }}</span
                >
                <span
                    class="w-24 text-right text-2xl font-black tabular-nums"
                    >{{ row.score }}</span
                >
            </li>
        </TransitionGroup>
    </main>
</template>
