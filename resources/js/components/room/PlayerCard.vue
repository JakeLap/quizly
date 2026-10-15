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
        class="flex h-[4.5rem] items-center gap-3 rounded-2xl bg-white/10 px-3 ring-1 ring-white/15 backdrop-blur transition-opacity duration-300"
        :class="{ 'opacity-50': !online }"
    >
        <PlayerAvatar :nickname="player.nickname" :color="player.color" />

        <div class="grid min-w-0 flex-1 gap-1.5">
            <div class="flex min-w-0 items-center gap-2">
                <p class="truncate text-lg leading-tight font-semibold">
                    {{ player.nickname }}
                </p>
                <span
                    v-if="player.isVip"
                    class="inline-flex shrink-0 items-center gap-1 rounded-full bg-amber-400/20 px-2 py-1 text-[11px] leading-none font-bold tracking-wide text-amber-300 uppercase"
                >
                    <Crown class="size-3" />
                    VIP
                </span>
            </div>
            <p
                class="flex items-center gap-1.5 text-xs leading-none text-white/70"
            >
                <span
                    class="size-2 shrink-0 rounded-full"
                    :class="online ? 'bg-emerald-400' : 'bg-white/40'"
                />
                {{ online ? t('Connected') : t('Offline') }}
            </p>
        </div>

        <button
            v-if="removable"
            type="button"
            class="grid size-8 shrink-0 place-items-center rounded-full text-white/40 transition hover:bg-white/15 hover:text-white focus-visible:bg-white/15 focus-visible:text-white focus-visible:outline-none"
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
