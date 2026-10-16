<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Check } from '@lucide/vue';
import { computed, watch } from 'vue';
import RangeSetting from '@/components/room/RangeSetting.vue';
import { useTrans } from '@/composables/useTrans';
import type { CategoryOption, Range, RoomState } from '@/types/room';

const props = defineProps<{
    room: RoomState;
    categoryOptions: CategoryOption[];
    limits: { rounds: Range; secondsPerRound: Range; players: Range };
    locales: string[];
    /** Where to save: the screen and the VIP's phone each have their own. */
    action: string;
    /** One column on a phone, so category names fit. */
    narrow?: boolean;
}>();

const { t } = useTrans();

const languageNames: Record<string, string> = { el: 'Ελληνικά', en: 'English' };

/** The room's current settings, in the form's shape. */
function saved() {
    return {
        rounds: props.room.settings.rounds,
        seconds_per_round: props.room.settings.seconds_per_round,
        max_players: props.room.settings.max_players,
        locale: props.room.locale,
        categories: props.room.categories.map((category) => category.id),
    };
}

function key(data: ReturnType<typeof saved>) {
    return JSON.stringify({
        ...data,
        categories: [...data.categories].sort((a, b) => a - b),
    });
}

const form = useForm(saved());

// Settings save themselves shortly after someone stops changing them.
let timer: ReturnType<typeof setTimeout> | undefined;

function save() {
    timer = undefined;
    form.patch(props.action, {
        preserveScroll: true,
        preserveState: true,
        // Saving in the background shouldn't flash the progress bar.
        showProgress: false,
        // Someone else may have changed something while this was saving.
        onSuccess: () => timer === undefined && follow(),
    });
}

watch(
    () => form.data(),
    (data) => {
        clearTimeout(timer);
        timer = undefined;

        // Nothing to save: e.g. the form just took on a change made elsewhere.
        if (key(data) !== key(saved())) {
            timer = setTimeout(save, 350);
        }
    },
    { deep: true },
);

/**
 * Take on changes made on the other device (the screen or the VIP's phone),
 * unless a change made here is about to be saved: then the latest one wins.
 */
function follow() {
    if (timer !== undefined || form.processing) {
        return;
    }

    const next = saved();

    if (key(next) !== key(form.data())) {
        Object.assign(form, next);
    }
}

watch(saved, follow, { deep: true });

function toggleCategory(id: number) {
    form.categories = form.categories.includes(id)
        ? form.categories.filter((selected) => selected !== id)
        : [...form.categories, id];
}

const availableQuestions = computed(() =>
    props.categoryOptions
        .filter((category) => form.categories.includes(category.id))
        .reduce(
            (sum, category) =>
                sum + (category.questionCounts[form.locale] ?? 0),
            0,
        ),
);
</script>

<template>
    <div class="grid gap-6">
        <div class="grid gap-2">
            <span class="text-sm font-medium text-white/80">{{
                t('Language')
            }}</span>
            <div class="grid grid-cols-2 gap-2 rounded-xl bg-black/20 p-1">
                <button
                    v-for="locale in locales"
                    :key="locale"
                    type="button"
                    class="rounded-lg px-3 py-2 text-sm font-semibold transition"
                    :class="
                        form.locale === locale
                            ? 'bg-white text-indigo-900 shadow'
                            : 'text-white/80 hover:bg-white/10'
                    "
                    @click="form.locale = locale"
                >
                    {{ languageNames[locale] ?? locale }}
                </button>
            </div>
        </div>

        <RangeSetting
            id="rounds"
            v-model="form.rounds"
            :label="t('Rounds')"
            :min="limits.rounds.min"
            :max="limits.rounds.max"
            :error="form.errors.rounds"
        />
        <RangeSetting
            id="seconds"
            v-model="form.seconds_per_round"
            :label="t('Time per question')"
            :min="limits.secondsPerRound.min"
            :max="limits.secondsPerRound.max"
            suffix="s"
            :error="form.errors.seconds_per_round"
        />
        <RangeSetting
            id="players"
            v-model="form.max_players"
            :label="t('Max players')"
            :min="Math.max(limits.players.min, room.players.length)"
            :max="limits.players.max"
            :error="form.errors.max_players"
        />

        <div class="grid gap-2">
            <div class="flex items-baseline justify-between">
                <span class="text-sm font-medium text-white/80">{{
                    t('Categories')
                }}</span>
                <span class="text-xs text-white/60">{{
                    t(':count questions', { count: availableQuestions })
                }}</span>
            </div>
            <div
                class="grid gap-2"
                :class="narrow ? 'grid-cols-1' : 'grid-cols-2'"
            >
                <button
                    v-for="category in categoryOptions"
                    :key="category.id"
                    type="button"
                    class="flex items-center gap-2 rounded-xl px-3 py-2 text-left text-sm ring-1 transition disabled:cursor-not-allowed disabled:opacity-40"
                    :class="
                        form.categories.includes(category.id)
                            ? 'bg-white/20 ring-white/50'
                            : 'bg-black/10 ring-white/10 hover:bg-white/10'
                    "
                    :disabled="
                        !form.categories.includes(category.id) &&
                        !category.questionCounts[form.locale]
                    "
                    @click="toggleCategory(category.id)"
                >
                    <span
                        class="flex size-5 shrink-0 items-center justify-center rounded-md ring-1 ring-white/40"
                        :style="
                            form.categories.includes(category.id)
                                ? { backgroundColor: category.color ?? '#fff' }
                                : {}
                        "
                    >
                        <Check
                            v-if="form.categories.includes(category.id)"
                            class="size-3.5"
                        />
                    </span>
                    <span class="min-w-0 flex-1 truncate">{{
                        category.names[form.locale] ?? category.slug
                    }}</span>
                    <span class="text-xs text-white/50 tabular-nums">{{
                        category.questionCounts[form.locale] ?? 0
                    }}</span>
                </button>
            </div>
            <p v-if="form.errors.categories" class="text-sm text-rose-300">
                {{ form.errors.categories }}
            </p>
        </div>
    </div>
</template>
