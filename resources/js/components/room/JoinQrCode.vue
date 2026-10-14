<script setup lang="ts">
import QRCode from 'qrcode';
import { ref, watchEffect } from 'vue';

const props = defineProps<{ url: string }>();

const dataUrl = ref<string>();

watchEffect(async () => {
    dataUrl.value = await QRCode.toDataURL(props.url, {
        margin: 1,
        width: 320,
        errorCorrectionLevel: 'M',
    });
});
</script>

<template>
    <div class="rounded-2xl bg-white p-3 shadow-xl">
        <img
            v-if="dataUrl"
            :src="dataUrl"
            :alt="url"
            class="size-40 [image-rendering:pixelated] xl:size-48"
        />
        <div v-else class="size-40 xl:size-48" />
    </div>
</template>
