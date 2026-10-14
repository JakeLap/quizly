<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        nickname: string;
        color: string;
        size?: 'sm' | 'md' | 'lg';
    }>(),
    { size: 'md' },
);

const initial = computed(
    () => Array.from(props.nickname.trim())[0]?.toLocaleUpperCase() ?? '?',
);
</script>

<template>
    <span
        class="inline-flex shrink-0 items-center justify-center rounded-full font-bold text-white shadow-inner select-none"
        :class="{
            'size-8 text-sm': size === 'sm',
            'size-12 text-xl': size === 'md',
            'size-20 text-4xl': size === 'lg',
        }"
        :style="{ backgroundColor: color }"
        aria-hidden="true"
    >
        {{ initial }}
    </span>
</template>
