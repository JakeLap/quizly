<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ChevronLeft,
    ChevronRight,
    Ellipsis,
    FilterX,
    Pencil,
    Plus,
    Search,
} from '@lucide/vue';
import { useDebounceFn } from '@vueuse/core';
import { computed, reactive, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useTrans } from '@/composables/useTrans';
import {
    difficultyLabels,
    percent,
    sourceLabels,
    statusClasses,
    statusLabels,
} from '@/lib/questions';
import {
    create,
    edit,
    index,
    status as statusRoute,
} from '@/routes/admin/questions';
import type {
    Paginated,
    QuestionOptions,
    QuestionStatus,
    QuestionSummary,
} from '@/types/admin';

type Filters = {
    search: string | null;
    category: number | null;
    status: QuestionStatus | null;
    difficulty: string | null;
    source: string | null;
    kind: string | null;
};

const props = defineProps<
    QuestionOptions & {
        questions: Paginated<QuestionSummary>;
        filters: Filters;
        /** The type filter's value for questions without a type. */
        noKind: string;
        statusCounts: Partial<Record<QuestionStatus | 'all', number>>;
    }
>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Questions', href: index() }],
    },
});

const { t } = useTrans();

// Select needs a value for "everything": 'all' stands for no filter.
const ALL = 'all';

const filters = reactive({
    search: props.filters.search ?? '',
    category: props.filters.category ? String(props.filters.category) : ALL,
    status: props.filters.status ?? ALL,
    difficulty: props.filters.difficulty ?? ALL,
    source: props.filters.source ?? ALL,
    kind: props.filters.kind ?? ALL,
});

function load() {
    const query = Object.fromEntries(
        Object.entries(filters).filter(
            ([, value]) => value !== '' && value !== ALL,
        ),
    );

    router.get(index.url(), query, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

const loadSoon = useDebounceFn(load, 300);

watch(() => filters.search, loadSoon);
watch(
    () => [
        filters.category,
        filters.status,
        filters.difficulty,
        filters.source,
        filters.kind,
    ],
    load,
);

const filtering = computed(
    () =>
        filters.search !== '' ||
        filters.category !== ALL ||
        filters.difficulty !== ALL ||
        filters.source !== ALL ||
        filters.kind !== ALL,
);

function clearFilters() {
    Object.assign(filters, {
        search: '',
        category: ALL,
        difficulty: ALL,
        source: ALL,
        kind: ALL,
    });
}

const statusTabs = computed(() => [
    { value: ALL, label: t('All'), count: props.statusCounts.all ?? 0 },
    ...(['published', 'review', 'draft', 'rejected'] as const).map((value) => ({
        value,
        label: t(statusLabels[value]),
        count: props.statusCounts[value] ?? 0,
    })),
]);

function setStatus(question: QuestionSummary, status: QuestionStatus) {
    router.patch(
        statusRoute.url(question.id),
        { status },
        { preserveScroll: true, preserveState: true },
    );
}

const numberFormat = new Intl.NumberFormat();
</script>

<template>
    <Head :title="t('Questions')" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                :title="t('Questions')"
                :description="
                    t('Write, edit and review the questions games are made of.')
                "
            />
            <Button as-child>
                <Link :href="create()">
                    <Plus />
                    {{ t('New question') }}
                </Link>
            </Button>
        </div>

        <!-- Status tabs: scroll sideways on narrow screens. -->
        <div class="-mx-4 overflow-x-auto px-4 md:mx-0 md:px-0">
            <div class="flex w-max gap-2">
                <Button
                    v-for="tab in statusTabs"
                    :key="tab.value"
                    size="sm"
                    :variant="
                        filters.status === tab.value ? 'default' : 'outline'
                    "
                    @click="filters.status = tab.value"
                >
                    {{ tab.label }}
                    <span class="tabular-nums opacity-70">{{
                        numberFormat.format(tab.count)
                    }}</span>
                </Button>
            </div>
        </div>

        <div
            class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 2xl:grid-cols-[minmax(0,1fr)_12rem_10rem_10rem_13rem_auto]"
        >
            <div class="relative sm:col-span-2 lg:col-span-4 2xl:col-span-1">
                <Search
                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="filters.search"
                    type="search"
                    class="pl-9"
                    :placeholder="t('Search questions, answers or #id')"
                    :aria-label="t('Search')"
                />
            </div>

            <Select v-model="filters.category">
                <SelectTrigger class="w-full" :aria-label="t('Category')">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem :value="ALL">{{
                        t('All categories')
                    }}</SelectItem>
                    <SelectItem
                        v-for="category in categories"
                        :key="category.id"
                        :value="String(category.id)"
                    >
                        {{ category.name }}
                    </SelectItem>
                </SelectContent>
            </Select>

            <Select v-model="filters.difficulty">
                <SelectTrigger class="w-full" :aria-label="t('Difficulty')">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem :value="ALL">{{
                        t('Any difficulty')
                    }}</SelectItem>
                    <SelectItem
                        v-for="difficulty in difficulties"
                        :key="difficulty"
                        :value="difficulty"
                    >
                        {{ t(difficultyLabels[difficulty]) }}
                    </SelectItem>
                </SelectContent>
            </Select>

            <Select v-model="filters.source">
                <SelectTrigger class="w-full" :aria-label="t('Source')">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem :value="ALL">{{ t('Any source') }}</SelectItem>
                    <SelectItem
                        v-for="source in sources"
                        :key="source"
                        :value="source"
                    >
                        {{ t(sourceLabels[source] ?? source) }}
                    </SelectItem>
                </SelectContent>
            </Select>

            <Select v-model="filters.kind">
                <SelectTrigger class="w-full" :aria-label="t('Type')">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem :value="ALL">{{ t('Any type') }}</SelectItem>
                    <SelectItem :value="noKind">{{ t('No type') }}</SelectItem>
                    <SelectItem
                        v-for="kind in kinds"
                        :key="kind"
                        :value="kind"
                        class="font-mono"
                    >
                        {{ kind }}
                    </SelectItem>
                </SelectContent>
            </Select>

            <Button
                v-if="filtering"
                variant="ghost"
                class="sm:col-span-2 sm:justify-self-start lg:col-span-4 2xl:col-span-1"
                @click="clearFilters"
            >
                <FilterX />
                {{ t('Clear') }}
            </Button>
        </div>

        <p
            v-if="questions.data.length === 0"
            class="rounded-xl border border-dashed p-10 text-center text-muted-foreground"
        >
            {{ t('No questions match these filters.') }}
        </p>

        <template v-else>
            <!-- Phones: one card per question. -->
            <div class="grid gap-3 md:hidden">
                <Card
                    v-for="question in questions.data"
                    :key="question.id"
                    class="gap-0 py-0"
                >
                    <CardContent class="flex items-start gap-2 p-4">
                        <Link
                            :href="edit(question.id)"
                            class="grid min-w-0 flex-1 gap-2"
                        >
                            <p class="line-clamp-3 font-medium">
                                {{ question.text ?? t('(no wording yet)') }}
                            </p>
                            <div
                                class="flex flex-wrap items-center gap-x-3 gap-y-1.5 text-xs text-muted-foreground"
                            >
                                <Badge
                                    variant="outline"
                                    :class="statusClasses[question.status]"
                                >
                                    {{ t(statusLabels[question.status]) }}
                                </Badge>
                                <span class="flex items-center gap-1.5">
                                    <span
                                        class="size-2 rounded-full"
                                        :style="{
                                            backgroundColor:
                                                question.category_color ??
                                                'currentColor',
                                        }"
                                    />
                                    {{ question.category }}
                                </span>
                                <span>{{
                                    t(difficultyLabels[question.difficulty])
                                }}</span>
                                <Badge
                                    v-if="question.kind"
                                    variant="outline"
                                    class="font-mono"
                                    >{{ question.kind }}</Badge
                                >
                                <span v-if="question.times_asked">
                                    {{
                                        t(':rate right of :count', {
                                            rate: percent(
                                                question.correct_rate,
                                            ),
                                            count: question.times_asked,
                                        })
                                    }}
                                </span>
                                <Badge
                                    v-for="locale in question.missing_locales"
                                    :key="locale"
                                    variant="outline"
                                >
                                    {{
                                        t('No :locale', {
                                            locale: locale.toUpperCase(),
                                        })
                                    }}
                                </Badge>
                            </div>
                        </Link>
                        <DropdownMenu>
                            <DropdownMenuTrigger as-child>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="-mt-1 -mr-2 shrink-0"
                                    :aria-label="t('Actions')"
                                >
                                    <Ellipsis />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <DropdownMenuItem as-child>
                                    <Link :href="edit(question.id)">
                                        <Pencil />
                                        {{ t('Edit') }}
                                    </Link>
                                </DropdownMenuItem>
                                <DropdownMenuSeparator />
                                <DropdownMenuLabel>{{
                                    t('Change status')
                                }}</DropdownMenuLabel>
                                <DropdownMenuItem
                                    v-for="status in statuses"
                                    :key="status"
                                    :disabled="status === question.status"
                                    @select="setStatus(question, status)"
                                >
                                    {{ t(statusLabels[status]) }}
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </CardContent>
                </Card>
            </div>

            <!-- Wider screens: a table. -->
            <div class="hidden overflow-hidden rounded-xl border md:block">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead class="w-full">{{
                                t('Question')
                            }}</TableHead>
                            <TableHead>{{ t('Category') }}</TableHead>
                            <TableHead>{{ t('Type') }}</TableHead>
                            <TableHead>{{ t('Difficulty') }}</TableHead>
                            <TableHead>{{ t('Status') }}</TableHead>
                            <TableHead class="text-right">{{
                                t('Answered right')
                            }}</TableHead>
                            <TableHead
                                ><span class="sr-only">{{
                                    t('Actions')
                                }}</span></TableHead
                            >
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="question in questions.data"
                            :key="question.id"
                        >
                            <TableCell class="max-w-0 whitespace-normal">
                                <Link
                                    :href="edit(question.id)"
                                    class="line-clamp-2 font-medium hover:underline"
                                >
                                    {{ question.text ?? t('(no wording yet)') }}
                                </Link>
                                <div
                                    class="mt-1 flex flex-wrap items-center gap-2 text-xs text-muted-foreground"
                                >
                                    <span>#{{ question.id }}</span>
                                    <span>{{
                                        t(
                                            sourceLabels[question.source] ??
                                                question.source,
                                        )
                                    }}</span>
                                    <Badge
                                        v-for="locale in question.missing_locales"
                                        :key="locale"
                                        variant="outline"
                                    >
                                        {{
                                            t('No :locale', {
                                                locale: locale.toUpperCase(),
                                            })
                                        }}
                                    </Badge>
                                    <span v-if="question.edited">{{
                                        t('Edited here')
                                    }}</span>
                                </div>
                            </TableCell>
                            <TableCell>
                                <span class="flex items-center gap-2">
                                    <span
                                        class="size-2 shrink-0 rounded-full"
                                        :style="{
                                            backgroundColor:
                                                question.category_color ??
                                                'currentColor',
                                        }"
                                    />
                                    {{ question.category }}
                                </span>
                            </TableCell>
                            <TableCell>
                                <span
                                    v-if="question.kind"
                                    class="font-mono text-xs"
                                    >{{ question.kind }}</span
                                >
                                <span v-else class="text-muted-foreground"
                                    >–</span
                                >
                            </TableCell>
                            <TableCell>{{
                                t(difficultyLabels[question.difficulty])
                            }}</TableCell>
                            <TableCell>
                                <Badge
                                    variant="outline"
                                    :class="statusClasses[question.status]"
                                >
                                    {{ t(statusLabels[question.status]) }}
                                </Badge>
                            </TableCell>
                            <TableCell class="text-right tabular-nums">
                                <template v-if="question.times_asked">
                                    {{ percent(question.correct_rate) }}
                                    <span class="text-muted-foreground"
                                        >/ {{ question.times_asked }}</span
                                    >
                                </template>
                                <span v-else class="text-muted-foreground"
                                    >–</span
                                >
                            </TableCell>
                            <TableCell>
                                <DropdownMenu>
                                    <DropdownMenuTrigger as-child>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            :aria-label="t('Actions')"
                                        >
                                            <Ellipsis />
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end">
                                        <DropdownMenuItem as-child>
                                            <Link :href="edit(question.id)">
                                                <Pencil />
                                                {{ t('Edit') }}
                                            </Link>
                                        </DropdownMenuItem>
                                        <DropdownMenuSeparator />
                                        <DropdownMenuLabel>{{
                                            t('Change status')
                                        }}</DropdownMenuLabel>
                                        <DropdownMenuItem
                                            v-for="status in statuses"
                                            :key="status"
                                            :disabled="
                                                status === question.status
                                            "
                                            @select="
                                                setStatus(question, status)
                                            "
                                        >
                                            {{ t(statusLabels[status]) }}
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <nav
                class="flex flex-wrap items-center justify-between gap-3 text-sm text-muted-foreground"
                :aria-label="t('Pages')"
            >
                <span>
                    {{
                        t(':from–:to of :total', {
                            from: numberFormat.format(questions.from ?? 0),
                            to: numberFormat.format(questions.to ?? 0),
                            total: numberFormat.format(questions.total),
                        })
                    }}
                </span>
                <div class="flex gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        :disabled="!questions.prev_page_url"
                        :as="questions.prev_page_url ? Link : 'button'"
                        :href="questions.prev_page_url ?? undefined"
                        preserve-state
                    >
                        <ChevronLeft />
                        {{ t('Previous') }}
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        :disabled="!questions.next_page_url"
                        :as="questions.next_page_url ? Link : 'button'"
                        :href="questions.next_page_url ?? undefined"
                        preserve-state
                    >
                        {{ t('Next') }}
                        <ChevronRight />
                    </Button>
                </div>
            </nav>
        </template>
    </div>
</template>
