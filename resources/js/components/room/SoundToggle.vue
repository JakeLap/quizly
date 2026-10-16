<script setup lang="ts">
import { Volume2, VolumeX } from '@lucide/vue';
import { useTrans } from '@/composables/useTrans';
import { phoneSoundOn, unlockAudio } from '@/lib/sound';

const { t } = useTrans();

function toggle() {
    phoneSoundOn.value = !phoneSoundOn.value;
    unlockAudio();
}
</script>

<template>
    <button
        type="button"
        class="flex size-10 shrink-0 items-center justify-center rounded-full bg-white/10 ring-1 ring-white/15 transition hover:bg-white/20"
        :class="{ 'text-white/50': !phoneSoundOn }"
        :aria-pressed="phoneSoundOn"
        :aria-label="t('Sounds')"
        :title="t('Sounds')"
        @click="toggle"
    >
        <Volume2 v-if="phoneSoundOn" class="size-5" />
        <VolumeX v-else class="size-5" />
    </button>
</template>
