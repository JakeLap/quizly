<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    from: number;
    to: number;
    now: number;
}>();

const remaining = computed(() => Math.max(0, props.to - props.now));
const fraction = computed(() =>
    props.to > props.from ? remaining.value / (props.to - props.from) : 0,
);
const seconds = computed(() => Math.ceil(remaining.value / 1000));
</script>

<template>
    <div class="flex items-center gap-4">
        <div class="h-3 flex-1 overflow-hidden rounded-full bg-white/15">
            <div
                class="h-full rounded-full transition-[width] duration-100 ease-linear"
                :class="fraction > 0.25 ? 'bg-amber-300' : 'bg-rose-400'"
                :style="{ width: `${fraction * 100}%` }"
            />
        </div>
        <span
            class="w-12 text-right text-3xl font-black tabular-nums"
            :class="{ 'text-rose-300': fraction <= 0.25 }"
        >
            {{ seconds }}
        </span>
    </div>
</template>
