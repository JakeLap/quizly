<script setup lang="ts">
import { animate } from '@/lib/motion';

/**
 * Swaps one piece of content for the next (a phase, a lobby slot, the answer
 * buttons…) with a short animation. Give the child a :key that changes when
 * the content does.
 */
const props = withDefaults(defineProps<{ variant?: 'rise' | 'pop' }>(), {
    variant: 'rise',
});

const hidden =
    props.variant === 'rise'
        ? { opacity: 0, transform: 'translateY(12px)' }
        : { opacity: 0, transform: 'scale(0.95)' };
const shown = { opacity: 1, transform: 'none' };

function onEnter(element: Element, done: () => void) {
    animate(
        element,
        [hidden, shown],
        { duration: props.variant === 'rise' ? 300 : 200, easing: 'ease-out' },
        done,
    );
}

function onLeave(element: Element, done: () => void) {
    animate(
        element,
        [shown, { ...hidden, transform: 'none' }],
        { duration: 150, easing: 'ease-in', fill: 'forwards' },
        done,
    );
}
</script>

<template>
    <Transition mode="out-in" :css="false" @enter="onEnter" @leave="onLeave">
        <slot />
    </Transition>
</template>
