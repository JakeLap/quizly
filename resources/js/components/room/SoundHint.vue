<script setup lang="ts">
import { Volume2 } from '@lucide/vue';
import { useTrans } from '@/composables/useTrans';
import { audioUnlocked, unlockAudio } from '@/lib/sound';

const { t } = useTrans();
</script>

<template>
    <!-- Browsers keep a page quiet until someone interacts with it, e.g. after
         the screen reloads. Any click starts the sound; this says so. -->
    <button
        v-if="!audioUnlocked"
        type="button"
        class="fixed top-4 left-1/2 z-40 flex -translate-x-1/2 animate-pulse items-center gap-2 rounded-full bg-amber-400 px-4 py-2 text-sm font-bold text-indigo-950 shadow-lg"
        @click="unlockAudio"
    >
        <Volume2 class="size-4" />
        {{ t('Click anywhere for sound') }}
    </button>
</template>
