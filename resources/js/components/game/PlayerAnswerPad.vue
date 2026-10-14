<script setup lang="ts">
import { Lock } from '@lucide/vue';
import { computed } from 'vue';
import CountdownBar from '@/components/game/CountdownBar.vue';
import { useTrans } from '@/composables/useTrans';
import { answerSlots } from '@/lib/answerSlots';
import type { RoomRound } from '@/types/room';

const props = defineProps<{
    round: RoomRound;
    now: number;
    /** The button this player pressed this round, if any. */
    chosen: number | null;
    error?: string;
}>();

const emit = defineEmits<{ answer: [slot: number] }>();

const { t } = useTrans();

const unlocked = computed(() => props.now >= props.round.opensAt);
const timeUp = computed(() => props.now > props.round.endsAt);
const secondsToUnlock = computed(() =>
    Math.max(1, Math.ceil((props.round.opensAt - props.now) / 1000)),
);
const canAnswer = computed(
    () => unlocked.value && !timeUp.value && props.chosen === null,
);

function choose(slot: number) {
    if (canAnswer.value) {
        navigator.vibrate?.(30);
        emit('answer', slot);
    }
}
</script>

<template>
    <section class="grid flex-1 grid-rows-[auto_1fr_auto] gap-4">
        <div class="grid gap-3">
            <p class="text-center text-sm font-semibold text-white/70">
                {{ round.category.name }} ·
                {{
                    t('Question :number of :total', {
                        number: round.number,
                        total: round.total,
                    })
                }}
            </p>
            <CountdownBar
                v-if="unlocked"
                :from="round.opensAt"
                :to="round.endsAt"
                :now="now"
            />
        </div>

        <div
            v-if="!unlocked"
            class="flex flex-col items-center justify-center gap-2 text-center"
        >
            <p class="text-2xl font-bold">
                {{ t('Read the question on the screen') }}
            </p>
            <p class="text-7xl font-black text-amber-300 tabular-nums">
                {{ secondsToUnlock }}
            </p>
        </div>

        <div
            v-else-if="chosen !== null"
            class="flex flex-col items-center justify-center gap-4 text-center"
        >
            <div
                class="flex size-28 items-center justify-center rounded-3xl shadow-xl"
                :class="answerSlots[chosen].bg"
            >
                <component
                    :is="answerSlots[chosen].icon"
                    class="size-14 fill-white/90 stroke-white"
                />
            </div>
            <p class="flex items-center gap-2 text-2xl font-black">
                <Lock class="size-6" /> {{ t('Answer locked in!') }}
            </p>
            <p class="text-white/70">{{ round.options[chosen] }}</p>
            <p class="text-sm text-white/60">
                {{ t('Waiting for the others…') }}
            </p>
        </div>

        <div v-else-if="timeUp" class="flex items-center justify-center">
            <p class="text-4xl font-black">{{ t("Time's up!") }}</p>
        </div>

        <div v-else class="grid grid-cols-2 grid-rows-2 gap-3">
            <button
                v-for="(option, slot) in round.options"
                :key="slot"
                type="button"
                class="flex flex-col items-center justify-center gap-2 rounded-3xl p-3 text-center text-lg leading-tight font-bold shadow-lg transition active:scale-95"
                :class="answerSlots[slot].bg"
                @click="choose(slot)"
            >
                <component
                    :is="answerSlots[slot].icon"
                    class="size-10 fill-white/90 stroke-white"
                />
                <span class="line-clamp-3">{{ option }}</span>
            </button>
        </div>

        <p v-if="error" class="text-center text-sm font-semibold text-rose-200">
            {{ error }}
        </p>
    </section>
</template>
