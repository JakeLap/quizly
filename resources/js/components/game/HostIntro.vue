<script setup lang="ts">
import { computed } from 'vue';
import PlayerAvatar from '@/components/room/PlayerAvatar.vue';
import { useTrans } from '@/composables/useTrans';
import type { RoomState } from '@/types/room';

const props = defineProps<{ room: RoomState; now: number }>();

const { t } = useTrans();

const secondsLeft = computed(() =>
    props.room.nextAt
        ? Math.max(0, Math.ceil((props.room.nextAt - props.now) / 1000))
        : 0,
);
</script>

<template>
    <main
        class="flex min-h-dvh flex-col items-center justify-center gap-8 p-10 text-center"
    >
        <p class="text-2xl text-white/70">{{ t('Get ready!') }}</p>
        <h1 class="text-6xl font-black lg:text-8xl">
            {{ t('The game is starting') }}
        </h1>
        <p
            class="text-9xl font-black text-amber-300 tabular-nums drop-shadow-lg"
        >
            {{ secondsLeft }}
        </p>
        <div class="flex flex-wrap justify-center gap-3">
            <div
                v-for="player in room.players"
                :key="player.id"
                class="flex items-center gap-2 rounded-full bg-white/10 py-1 pr-4 pl-1"
            >
                <PlayerAvatar
                    :nickname="player.nickname"
                    :color="player.color"
                    size="sm"
                />
                <span class="font-semibold">{{ player.nickname }}</span>
            </div>
        </div>
        <p class="text-white/60">
            {{
                t(':rounds rounds · :seconds seconds per question', {
                    rounds: room.settings.rounds,
                    seconds: room.settings.seconds_per_round,
                })
            }}
        </p>
    </main>
</template>
