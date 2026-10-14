<script setup lang="ts">
defineProps<{
    id: string;
    label: string;
    min: number;
    max: number;
    suffix?: string;
    error?: string;
}>();

const model = defineModel<number>({ required: true });
</script>

<template>
    <div class="grid gap-2">
        <div class="flex items-baseline justify-between">
            <label :for="id" class="text-sm font-medium text-white/80">
                {{ label }}
            </label>
            <span class="text-xl font-bold tabular-nums">
                {{ model
                }}<span v-if="suffix" class="text-sm">{{ suffix }}</span>
            </span>
        </div>
        <input
            :id="id"
            v-model.number="model"
            type="range"
            :min="min"
            :max="max"
            class="w-full accent-amber-400"
        />
        <p v-if="error" class="text-sm text-rose-300">{{ error }}</p>
    </div>
</template>
