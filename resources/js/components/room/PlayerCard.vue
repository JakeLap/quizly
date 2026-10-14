<script setup lang="ts">
import { Crown, X } from '@lucide/vue';
import PlayerAvatar from '@/components/room/PlayerAvatar.vue';
import { useTrans } from '@/composables/useTrans';
import type { RoomPlayer } from '@/types/room';

defineProps<{
    player: RoomPlayer;
    online: boolean;
    removable?: boolean;
}>();

defineEmits<{ remove: [] }>();

const { t } = useTrans();
</script>

<template>
    <div
        class="group relative flex items-center gap-3 rounded-2xl bg-white/10 p-3 pr-4 ring-1 ring-white/15 backdrop-blur transition"
        :class="{ 'opacity-50': !online }"
    >
        <PlayerAvatar :nickname="player.nickname" :color="player.color" />

        <div class="min-w-0 flex-1">
            <p class="truncate text-lg font-semibold">
                {{ player.nickname }}
            </p>
            <p class="flex items-center gap-1.5 text-xs text-white/70">
                <span
                    class="size-2 rounded-full"
                    :class="online ? 'bg-emerald-400' : 'bg-white/40'"
                />
                {{ online ? t('Connected') : t('Offline') }}
                <template v-if="player.isVip">
                    ·
                    <Crown class="size-3.5 text-amber-300" />
                    VIP
                </template>
            </p>
        </div>

        <button
            v-if="removable"
            type="button"
            class="rounded-full p-1.5 text-white/60 opacity-0 transition group-hover:opacity-100 hover:bg-white/15 hover:text-white focus-visible:opacity-100"
            :title="t('Remove :name', { name: player.nickname })"
            @click="$emit('remove')"
        >
            <X class="size-4" />
            <span class="sr-only">{{
                t('Remove :name', { name: player.nickname })
            }}</span>
        </button>
    </div>
</template>
