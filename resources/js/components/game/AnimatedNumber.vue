<script setup lang="ts">
import { onBeforeUnmount, ref, watch } from 'vue';
import { prefersReducedMotion } from '@/lib/motion';

const props = withDefaults(
    defineProps<{ value: number; duration?: number }>(),
    {
        duration: 900,
    },
);

/**
 * Counts smoothly to each new value. If the browser isn't rendering frames
 * (a hidden tab), the final value is shown once the time is up anyway.
 */
const shown = ref(props.value);
let frame = 0;
let fallback = 0;

function stop() {
    cancelAnimationFrame(frame);
    window.clearTimeout(fallback);
}

watch(
    () => props.value,
    (to) => {
        stop();

        const from = shown.value;

        if (prefersReducedMotion() || from === to) {
            shown.value = to;

            return;
        }

        const start = performance.now();
        const step = (time: number) => {
            const progress = Math.min(1, (time - start) / props.duration);
            const eased = 1 - (1 - progress) ** 3;
            shown.value = from + (to - from) * eased;

            if (progress < 1) {
                frame = requestAnimationFrame(step);
            }
        };

        frame = requestAnimationFrame(step);
        fallback = window.setTimeout(() => {
            stop();
            shown.value = to;
        }, props.duration + 150);
    },
);

onBeforeUnmount(stop);
</script>

<template>
    <span class="tabular-nums">{{ Math.round(shown) }}</span>
</template>
